<?php
namespace Espo\Custom\Services\Logistics;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Custom\Services\ShipmentTotalsCalculator;
use Espo\Custom\Services\OrderTotalsCalculator;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\NotFound;

final class Finance
{
    public function __construct(private EntityManager $em, private Access $access, private Stock $stock,
        private ShipmentTotalsCalculator $totals, private OrderTotalsCalculator $orderTotals) {}

    private const SAVE = ['logisticsInternal' => true, 'silent' => true, 'skipHooks' => true];

    public function execute(string $operation, array $data): array
    {
        if (!in_array($operation, ['confirm','cancel','receipt','allocate','opening','return'], true)) {
            throw new BadRequest('Thao tác không hợp lệ.');
        }
        $accountId = (string) ($data['accountId'] ?? '');
        $account = $this->access->account($accountId, 'CFinance', $operation === 'confirm' ? 'read' : 'edit');
        $key = (string) ($data['requestKey'] ?? '');
        if (!preg_match('/^[a-zA-Z0-9_-]{8,80}$/D', $key)) { throw new BadRequest('Thiếu mã yêu cầu.'); }
        $hash = hash('sha256', json_encode([$operation, $this->access->ownId(), $data], JSON_THROW_ON_ERROR));
        return $this->em->getTransactionManager()->run(function () use ($operation,$data,$account,$accountId,$key,$hash) {
            // Serialize every financial operation for this customer. This also
            // protects pooled credit and allocations across multiple invoices.
            $this->lock('account', $accountId);
            $previous = $this->em->getRepository('CLogisticsOperation')->where(['requestKey' => $key])->findOne();
            if ($previous) {
                if ($previous->get('accountId') !== $accountId || $previous->get('payloadHash') !== $hash) {
                    throw new Conflict('Mã yêu cầu đã được sử dụng cho thao tác khác.');
                }
                return json_decode($previous->get('result'), true, 512, JSON_THROW_ON_ERROR);
            }
            switch ($operation) {
                case 'confirm': $this->confirm($account, $data); break;
                case 'cancel': $this->cancel($accountId, $data); break;
                case 'return': $this->returnGoods($accountId, $data); break;
                case 'receipt':
                    $receipt = $this->entry($accountId, 'receipt', Money::positive($data['amountVnd'] ?? null), null,
                        (string) ($data['reason'] ?? ''));
                    $this->allocate($accountId, $data['allocations'] ?? [], $receipt->getId());
                    break;
                case 'allocate': $this->allocate($accountId, $data['allocations'] ?? []); break;
                case 'opening':
                    if (!in_array($data['kind'] ?? '', ['openingDebit','openingCredit'], true)) { throw new BadRequest(); }
                    $reason = trim((string) ($data['reason'] ?? ''));
                    if ($reason === '') { throw new BadRequest('Vui lòng ghi lý do số dư đầu kỳ.'); }
                    $this->entry($accountId, $data['kind'], Money::positive($data['amountVnd'] ?? null), null, $reason);
                    break;
            }
            $this->refreshPayments($accountId);
            $result = $this->balance($accountId);
            $record = $this->em->getNewEntity('CLogisticsOperation');
            $record->set(['name' => $operation, 'accountId' => $accountId, 'requestKey' => $key,
                'payloadHash' => $hash, 'result' => json_encode($result, JSON_THROW_ON_ERROR)]);
            $this->em->saveEntity($record, self::SAVE);
            return $result;
        });
    }

    private function lock(string $table, string $id): void
    {
        if (!in_array($table, ['account','c_shipment','c_order','c_parcel'], true)) { throw new \LogicException(); }
        $query = $this->em->getPDO()->prepare("SELECT id FROM `$table` WHERE id = ? AND deleted = 0 FOR UPDATE");
        $query->execute([$id]);
        if (!$query->fetchColumn()) { throw new NotFound(); }
    }

    private function shipment(string $accountId, array $data): Entity
    {
        $id = (string) ($data['shipmentId'] ?? '');
        $this->lock('c_shipment', $id);
        $s = $this->em->getEntity('CShipment', $id);
        if (!$s || $s->get('accountId') !== $accountId) { throw new BadRequest('Phiếu không thuộc khách hàng này.'); }
        $this->access->shipment($s);
        return $s;
    }

    private function confirm(Entity $account, array $data): void
    {
        $s = $this->shipment($account->getId(), $data);
        if (!in_array($s->get('workflowStatus'), [null,'','draft'], true)) { throw new Conflict('Phiếu không còn là bản nháp.'); }
        if ($s->get('paid')) { throw new Conflict('Phiếu cũ đã thanh toán cần đối soát trước khi đưa vào sổ công nợ.'); }
        $orders = $this->orders($s);
        $ids = array_keys($orders); sort($ids);
        foreach ($ids as $id) { $this->lock('c_order', $id); }
        $rows = []; $remaining = 0;
        foreach ($ids as $id) {
            $o = $this->em->getEntity('COrder', $id);
            if (!$o || !$this->access->canRead($o)) { throw new \Espo\Core\Exceptions\Forbidden(); }
            if ($o->get('accountId') !== $account->getId()) { throw new BadRequest('Các đơn phải cùng khách hàng với PXK.'); }
            // Holding order row locks prevents concurrent cross-shipment posting.
            $used = $this->em->getRepository('CShipmentCharge')->where(['orderId'=>$id,'active'=>true])->findOne();
            if ($used) { throw new Conflict('Đơn đã nằm trong một phiếu được xác nhận: ' . $o->get('name')); }
            $this->orderTotals->apply($o);
            $row = ['id'=>$id, 'name'=>$o->get('name')];
            foreach (['totalOrderValueVnd','declaredValueVnd','serviceFeeVnd','documentFeeVnd','entrustmentVnd',
                'importTaxVnd','vatVnd','depositAmountVnd','remainingAmountVnd','serviceFeeRatePercent',
                'entrustmentPercent','importTaxPercent','vatPercent'] as $field) { $row[$field] = $o->get($field); }
            $remaining += Money::minor($o->get('remainingAmountVnd') ?? 0);
            $rows[] = $row;
        }
        if (count($rows) > 100) { throw new BadRequest('Tối đa 100 đơn trên một phiếu.'); }
        $parcels = [];
        foreach ($this->em->getRepository('CParcel')->where(['shipmentId'=>$s->getId()])->find() as $p) {
            $this->lock('c_parcel', $p->getId());
            if ($p->get('accountId') !== $account->getId()) { throw new BadRequest('Tracking không thuộc khách hàng của phiếu.'); }
            if ($p->get('status') === 'delivered') { throw new Conflict('Tracking đã giao: ' . $p->get('name')); }
            $parcels[] = ['id'=>$p->getId(),'name'=>$p->get('name'),'weight'=>$p->get('weight'),
                'volume'=>$p->get('volume'),'packageCount'=>$p->get('packageCount')];
        }
        if (count($parcels) > 500) { throw new BadRequest('Tối đa 500 tracking trên một phiếu.'); }
        $this->totals->apply($s);
        $s->set('orderRemainingAmountVnd', Money::major($remaining));
        $amount = $remaining + Money::minor($s->get('totalDeliveryPriceVnd') ?? 0) + Money::minor($s->get('additionalAmountVnd') ?? 0);
        if ($amount < 0) { throw new BadRequest('Phiếu âm phải được xử lý bằng số dư khách hoặc phiếu hoàn.'); }
        $old = $this->rawBalance($account->getId())['balance'];
        $now = gmdate('Y-m-d H:i:s');
        $snapshot = ['number'=>$s->get('name'),'accountCode'=>$account->get('name'),'accountName'=>$account->get('cName'),
            'date'=>$now . ' UTC','orders'=>$rows,'parcels'=>$parcels,'freight'=>$s->get('totalDeliveryPriceVnd'),
            'additional'=>$s->get('additionalAmountVnd'),'surcharge'=>$s->get('surcharge'),
            'amount'=>Money::major($amount),'oldBalance'=>Money::major($old),'requested'=>Money::major(max(0,$amount+$old))];
        $s->set(['workflowStatus'=>'confirmed','confirmedAt'=>$now,'postedAmountVnd'=>Money::major($amount),
            'totalPayableAmountVnd'=>Money::major($amount),'oldBalanceSnapshotVnd'=>Money::major($old),
            'requestedTotalSnapshotVnd'=>Money::major(max(0,$amount+$old)),
            'invoiceSnapshot'=>json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'invoiceSnapshotHtml'=>Snapshot::html($snapshot)]);
        $this->em->saveEntity($s, self::SAVE);
        foreach ($rows as $row) {
            $line = $this->em->getNewEntity('CShipmentCharge');
            $line->set(['name'=>$row['name'],'accountId'=>$account->getId(),'shipmentId'=>$s->getId(),
                'orderId'=>$row['id'],'amountVnd'=>$row['remainingAmountVnd'],'active'=>true]);
            $this->em->saveEntity($line, self::SAVE);
        }
    }

    public function orders(Entity $s): array
    {
        $result = [];
        if ($s->get('orderId')) {
            $o = $this->em->getEntity('COrder', $s->get('orderId'));
            if (!$o) { throw new BadRequest('Đơn hàng chính không còn tồn tại.'); }
            $result[$o->getId()] = $o;
        }
        $extra=$s->get('additionalOrders')??[];
        if (!is_array($extra) || count($extra)>100) { throw new BadRequest('Danh sách đơn không hợp lệ.'); }
        foreach ($extra as $id) {
            if (!is_string($id)) { throw new BadRequest('Mã đơn không hợp lệ.'); }
            $o=$this->em->getEntity('COrder',$id);
            if (!$o) { throw new BadRequest('Đơn hàng không còn tồn tại.'); }
            $result[$o->getId()]=$o;
        }
        return $result;
    }

    private function cancel(string $accountId, array $data): void
    {
        $s = $this->shipment($accountId, $data);
        if ($s->get('workflowStatus') === 'cancelled') { throw new Conflict('Phiếu đã hủy.'); }
        if (!in_array($s->get('workflowStatus'), ['draft','confirmed'], true)) {
            throw new Conflict('Phiếu cũ cần đối soát công nợ trước khi hủy trong sổ mới.');
        }
        $reason = trim((string) ($data['reason'] ?? ''));
        if (!$reason) { throw new BadRequest('Vui lòng ghi lý do hủy phiếu.'); }
        $balance = $this->rawBalance($accountId);
        $paid = $balance['shipments'][$s->getId()]['paid'] ?? 0;
        if ($paid > 0) { $this->entry($accountId,'release',$paid,$s->getId(),$reason); }
        $s->set(['workflowStatus'=>'cancelled','cancelledAt'=>gmdate('Y-m-d H:i:s'),'paid'=>false,
            'paidAmountVnd'=>0,'outstandingAmountVnd'=>0]);
        $this->em->saveEntity($s,self::SAVE);
        foreach ($this->em->getRepository('CShipmentCharge')->where(['shipmentId'=>$s->getId(),'active'=>true])->find() as $line) {
            $line->set('active',false); $this->em->saveEntity($line,self::SAVE);
        }
        foreach ($this->em->getRepository('CParcel')->where(['shipmentId'=>$s->getId()])->find() as $p) {
            $this->stock->move($p,'undelivered','cancelled',$s->getId(),$reason);
            $p->set('shipmentId',null); $this->em->saveEntity($p,self::SAVE);
        }
    }

    private function returnGoods(string $accountId, array $data): void
    {
        $s = $this->shipment($accountId, $data);
        if ($s->get('workflowStatus') !== 'confirmed') { throw new Conflict('Chỉ hoàn hàng của phiếu đã xác nhận.'); }
        $ids = $data['parcelIds'] ?? [];
        if (!is_array($ids) || !$ids || count($ids)>500 ||
            count(array_filter($ids, static fn($id) => is_string($id) && $id !== '')) !== count($ids) ||
            count(array_unique($ids))!==count($ids)) {
            throw new BadRequest('Vui lòng chọn tracking hoàn, không trùng lặp.');
        }
        $reason = trim((string) ($data['reason'] ?? ''));
        if (!$reason) { throw new BadRequest('Vui lòng nhập lý do hoàn hàng.'); }
        $credit = Money::minor($data['amountVnd'] ?? 0);
        $row = $this->rawBalance($accountId)['shipments'][$s->getId()];
        if ($credit < 0 || $credit > $row['net']) { throw new BadRequest('Số tiền hoàn vượt giá trị còn lại của phiếu.'); }
        sort($ids);
        foreach ($ids as $id) {
            $this->lock('c_parcel',(string)$id); $p=$this->em->getEntity('CParcel',(string)$id);
            if ($p->get('shipmentId')!==$s->getId() || $p->get('accountId')!==$accountId || $p->get('status')!=='delivered') {
                throw new Conflict('Tracking không còn ở trạng thái đã giao của phiếu này.');
            }
        }
        foreach ($ids as $id) {
            $p=$this->em->getEntity('CParcel',(string)$id);
            $this->stock->move($p,'returned','returned',$s->getId(),$reason);
            $p->set('shipmentId',null); $this->em->saveEntity($p,self::SAVE);
        }
        $return=$this->entry($accountId,'returnCredit',$credit,$s->getId(),$reason);
        $tracking=[];
        foreach ($ids as $id) { $p=$this->em->getEntity('CParcel',$id); $tracking[]=['id'=>$id,'name'=>$p->get('name')]; }
        $return->set('trackingSnapshot',json_encode($tracking,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        $this->em->saveEntity($return,self::SAVE);
        $excess=max(0,$row['paid']-($row['net']-$credit));
        if ($excess>0) { $this->entry($accountId,'release',$excess,$s->getId(),'Tiền dư sau hoàn hàng: '.$reason); }
    }

    private function allocate(string $accountId, mixed $allocations, ?string $receiptId = null): void
    {
        if (!is_array($allocations) || count($allocations)>100) { throw new BadRequest('Danh sách phân bổ không hợp lệ.'); }
        foreach ($allocations as $allocation) {
            $allocation=(array)$allocation; $id=(string)($allocation['shipmentId']??'');
            $amount=Money::positive($allocation['amountVnd']??null);
            $b=$this->rawBalance($accountId);
            if ($amount>$b['credit']) { throw new BadRequest('Không đủ tiền thu / tiền dư để phân bổ.'); }
            if ($id==='') { $due=$b['openingDue']; }
            else {
                $s=$this->shipment($accountId,['shipmentId'=>$id]);
                if ($s->get('workflowStatus')!=='confirmed') { throw new BadRequest('Chỉ thanh toán phiếu đã xác nhận.'); }
                $due=$b['shipments'][$id]['due'];
            }
            if ($amount>$due) { throw new BadRequest('Phân bổ vượt số còn nợ của phiếu. Phần dư giữ ở khách hàng.'); }
            $this->entry($accountId,'allocation',$amount,$id?:null,'',$receiptId);
        }
    }

    private function entry(string $accountId, string $kind, int $amount, ?string $shipmentId,
        string $reason='', ?string $receiptId=null): Entity
    {
        $e=$this->em->getNewEntity('CLogisticsEntry');
        $e->set(['name'=>$kind.' '.gmdate('Y-m-d H:i:s'),'accountId'=>$accountId,'kind'=>$kind,
            'amountVnd'=>Money::major($amount),'shipmentId'=>$shipmentId,'receiptId'=>$receiptId,'reason'=>$reason]);
        $this->em->saveEntity($e,self::SAVE); return $e;
    }

    private function rawBalance(string $accountId): array
    {
        $shipments=[]; $entries=[];
        foreach ($this->em->getRepository('CShipment')->where(['accountId'=>$accountId,
            'workflowStatus'=>['confirmed','cancelled']])->find() as $s) {
            $shipments[]=['id'=>$s->getId(),'workflowStatus'=>$s->get('workflowStatus'),'postedAmountVnd'=>$s->get('postedAmountVnd')??0];
        }
        foreach ($this->em->getRepository('CLogisticsEntry')->where(['accountId'=>$accountId])->find() as $e) {
            $entries[]=['kind'=>$e->get('kind'),'amountVnd'=>$e->get('amountVnd'),'shipmentId'=>$e->get('shipmentId')];
        }
        return Balance::calculate($shipments,$entries);
    }

    private function refreshPayments(string $accountId): void
    {
        $b=$this->rawBalance($accountId);
        if ($b['credit']<0) { throw new \LogicException('Negative available credit'); }
        foreach ($b['shipments'] as $id=>$row) {
            $s=$this->em->getEntity('CShipment',$id);
            $s->set(['paidAmountVnd'=>Money::major($row['paid']), 'outstandingAmountVnd'=>Money::major($row['due']),
                'paid'=>$s->get('workflowStatus')==='confirmed' && $row['due']===0]);
            $this->em->saveEntity($s,self::SAVE);
            if ($s->get('paid')) { $this->stock->paidShipment($s); }
        }
    }

    public function balance(string $accountId): array
    {
        $account=$this->access->account($accountId,'CFinance');
        $b=$this->rawBalance($accountId); $shipments=[]; $history=[];
        foreach ($b['shipments'] as $id=>$row) {
            $s=$this->em->getEntity('CShipment',$id);
            $shipments[]=['id'=>$id,'name'=>$s->get('name'),'status'=>$s->get('workflowStatus'),
                'amountVnd'=>Money::major($row['net']),'paidVnd'=>Money::major($row['paid']),
                'dueVnd'=>Money::major($row['due'])];
        }
        foreach ($this->em->getRepository('CLogisticsEntry')->where(['accountId'=>$accountId])->order('createdAt','DESC')->limit(0,200)->find() as $e) {
            $history[]=['id'=>$e->getId(),'kind'=>$e->get('kind'),'amountVnd'=>$e->get('amountVnd'),
                'shipmentId'=>$e->get('shipmentId'),'reason'=>$e->get('reason'),'createdAt'=>$e->get('createdAt'),
                'tracking'=>json_decode($e->get('trackingSnapshot')??'[]',true)];
        }
        return ['accountId'=>$accountId,'accountName'=>$account->get('cName'),'accountCode'=>$account->get('name'),
            'dueVnd'=>Money::major($b['due']),'creditVnd'=>Money::major($b['credit']),
            'balanceVnd'=>Money::major($b['balance']),'openingDueVnd'=>Money::major($b['openingDue']),
            'shipments'=>$shipments,'history'=>$history,'canEdit'=>$this->access->canEditFinance($accountId)];
    }

    public function orderOptions(string $accountId): array
    {
        $this->access->account($accountId,'CFinance'); $list=[];
        foreach ($this->em->getRepository('COrder')->where(['accountId'=>$accountId])->order('createdAt','DESC')->limit(0,500)->find() as $o) {
            if (!$this->access->canRead($o)) { continue; }
            $list[]=['id'=>$o->getId(),'name'=>$o->get('name')];
        }
        return ['list'=>$list];
    }

    public function returnOptions(string $accountId, string $shipmentId): array
    {
        $this->access->account($accountId,'CFinance');
        $s=$this->em->getEntity('CShipment',$shipmentId);
        if (!$s || $s->get('accountId')!==$accountId) { throw new NotFound(); }
        $this->access->shipment($s,'read'); $list=[];
        foreach ($this->em->getRepository('CParcel')->where(['shipmentId'=>$shipmentId,'status'=>'delivered'])->find() as $p) {
            $list[]=['id'=>$p->getId(),'name'=>$p->get('name')];
        }
        return ['list'=>$list];
    }
}
