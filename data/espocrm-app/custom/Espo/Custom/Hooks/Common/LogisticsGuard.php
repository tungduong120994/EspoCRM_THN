<?php
namespace Espo\Custom\Hooks\Common;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;

class LogisticsGuard
{
    public static int $order=1;
    private const IMMUTABLE=['CLogisticsEntry','CLogisticsOperation','CShipmentCharge','CStockEvent'];
    public function __construct(private EntityManager $em) {}

    public function beforeSave(Entity $e, array $options=[]): void
    {
        if (!empty($options['logisticsInternal'])) { return; }
        $type=$e->getEntityType();
        if (in_array($type,self::IMMUTABLE,true)) { throw new Forbidden('Chứng từ chỉ được ghi qua nghiệp vụ thu tiền / hoàn hàng.'); }
        if ($type==='CShipment') {
            $current=$e->isNew()?null:$this->em->getEntity('CShipment',$e->getId());
            $locked=$current && in_array($current->get('workflowStatus'),['confirmed','cancelled'],true);
            $protected=['workflowStatus','confirmedAt','cancelledAt','postedAmountVnd','paidAmountVnd',
                'outstandingAmountVnd','oldBalanceSnapshotVnd','requestedTotalSnapshotVnd','invoiceSnapshot','invoiceSnapshotHtml'];
            if ($locked) {
                $protected=array_merge($protected,['accountId','orderId','additionalOrders','paid','unitDeliveryPriceVnd',
                    'deliveryPricingMode','unitDeliveryPricePerKgVnd','unitDeliveryPricePerM3Vnd','additionalAmountVnd',
                    'totalPayableAmountVnd','totalDeliveryPriceVnd','surcharge']);
            } elseif ($current && $current->get('workflowStatus')==='draft' && $e->isAttributeChanged('paid')) {
                throw new BadRequest('Ghi nhận thanh toán tại trang Thu tiền và công nợ.');
            }
            foreach ($protected as $field) {
                if ($e->isNew() && $field==='workflowStatus' && in_array($e->get($field),[null,'','draft'],true)) { continue; }
                if ($e->isAttributeChanged($field)) { throw new Conflict('Dùng thao tác xác nhận / hủy / thanh toán; không sửa trực tiếp chứng từ.'); }
            }
            if ($e->isNew()) { $e->set('workflowStatus','draft'); $e->set('paid',false); }
        }
        if ($type==='CParcel') {
            foreach (['deliveredAt','returnedAt'] as $field) {
                if ($e->isAttributeChanged($field)) { throw new Forbidden('Ngày giao/hoàn được ghi tự động.'); }
            }
            $old=$e->getFetched('shipmentId'); $new=$e->get('shipmentId');
            foreach (array_unique(array_filter([$old,$new])) as $id) {
                $s=$this->em->getEntity('CShipment',$id);
                if (!$s) { throw new BadRequest('Phiếu không tồn tại.'); }
                if ($id===$new && $s->get('accountId') && $s->get('accountId')!==$e->get('accountId')) {
                    throw new BadRequest('Tracking và phiếu phải cùng khách hàng.');
                }
                if (in_array($s->get('workflowStatus'),['confirmed','cancelled'],true)) {
                    foreach (['shipmentId','accountId','weight','volume','packageCount','status','name'] as $field) {
                        if ($e->isAttributeChanged($field)) { throw new Conflict('Phiếu đã chốt. Hãy hủy phiếu hoặc lập phiếu hoàn trước.'); }
                    }
                }
            }
            if ($e->isAttributeChanged('status') && in_array($e->get('status'),['delivered','returned'],true)) {
                throw new BadRequest('Trạng thái đã giao/hoàn được ghi qua thanh toán hoặc phiếu hoàn.');
            }
        }
        if ($type==='COrder' || $type==='COrderItem') {
            $ids=$type==='COrder'?[$e->getId()]:array_filter([$e->get('orderId'),$e->getFetched('orderId')]);
            foreach ($ids as $id) {
                if ($id && $this->em->getRepository('CShipmentCharge')->where(['orderId'=>$id,'active'=>true])->findOne()) {
                    if ($type==='COrderItem' || $this->changedOrderCharges($e)) {
                        throw new Conflict('Đơn đã chốt trên PXK. Hủy phiếu trước khi sửa giá trị đơn.');
                    }
                }
            }
        }
    }

    private function changedOrderCharges(Entity $e): bool
    {
        foreach (['accountId','exchangeRate','domesticShippingFeeCny','serviceFeeVndPercent','serviceFeeRatePercent',
            'formalImport','declaredValueVnd','documentFeeVnd','entrustmentPercent','importTaxPercent','vatPercent',
            'depositAmountVnd','additionalAmountVnd','estimatedChinaVietnamShippingWeightKg',
            'estimatedChinaVietnamShippingUnitPriceVnd'] as $field) {
            if ($e->isAttributeChanged($field)) { return true; }
        }
        return false;
    }

    public function beforeRemove(Entity $e, array $options=[]): void
    {
        if (!empty($options['logisticsInternal'])) { return; }
        if (in_array($e->getEntityType(),self::IMMUTABLE,true)) { throw new Forbidden('Không xóa lịch sử chứng từ.'); }
        if ($e->getEntityType()==='CShipment' && in_array($e->get('workflowStatus'),['confirmed','cancelled'],true)) {
            throw new Conflict('Giữ phiếu đã chốt để đối soát; sử dụng Hủy phiếu.');
        }
        if ($e->getEntityType()==='CShipment' && ($e->get('paid') ||
            $this->em->getRepository('CParcel')->where(['shipmentId'=>$e->getId()])->findOne())) {
            throw new Conflict('Không xóa phiếu đã thanh toán hoặc còn tracking; hãy xử lý hủy/gỡ kiện trước.');
        }
        if ($e->getEntityType()==='CParcel' && $e->get('shipmentId')) {
            $s=$this->em->getEntity('CShipment',$e->get('shipmentId'));
            if ($s && in_array($s->get('workflowStatus'),['confirmed','cancelled'],true)) { throw new Conflict('Không xóa tracking của phiếu đã chốt.'); }
        }
        if (in_array($e->getEntityType(),['COrder','COrderItem'],true)) {
            $id=$e->getEntityType()==='COrder'?$e->getId():$e->get('orderId');
            if ($id && $this->em->getRepository('CShipmentCharge')->where(['orderId'=>$id,'active'=>true])->findOne()) { throw new Conflict('Không xóa đơn/dòng đơn của phiếu đã chốt.'); }
        }
    }
}
