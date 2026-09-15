<?php
namespace Espo\Custom\Services\Logistics;

use Espo\ORM\EntityManager;
use Espo\Core\Exceptions\BadRequest;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class Inventory
{
    public function __construct(private EntityManager $em, private Access $access) {}

    public function accounts(array $params, string $scope='CInventory', bool $export=false): array
    {
        $level=$this->access->level($scope);
        if ($export && $this->access->level($scope,'export')==='own') { $level='own'; }
        [$from,$to]=$this->dates($params);
        $q=trim((string)($params['q']??''));
        $where='a.deleted = 0'; $bind=[];
        if ($level==='own') { $where.=' AND a.assigned_user_id = ?'; $bind[]=$this->access->ownId(); }
        if ($q!=='') { $where.=' AND (a.name LIKE ? OR a.c_name LIKE ?)'; $bind[]='%'.$q.'%'; $bind[]='%'.$q.'%'; }
        $count=$this->em->getPDO()->prepare('SELECT COUNT(*) FROM account a WHERE '.$where);
        $count->execute($bind); $total=(int)$count->fetchColumn();
        $offset=max(0,(int)($params['offset']??0)); $limit=$export?10000:50;
        if ($export && $total>$limit) { throw new BadRequest('Trên 10.000 khách hàng. Hãy lọc hẹp hơn trước khi xuất.'); }
        $sql="SELECT a.id, a.name AS code, a.c_name AS name,
            COUNT(p.id) AS totalTracking,
            COALESCE(SUM(CASE WHEN p.created_at >= ? AND p.created_at < ? THEN 1 ELSE 0 END),0) AS receivedTracking,
            COALESCE(SUM(CASE WHEN p.created_at >= ? AND p.created_at < ? THEN p.weight ELSE 0 END),0) AS receivedWeight,
            COALESCE(SUM(CASE WHEN p.created_at >= ? AND p.created_at < ? THEN p.volume ELSE 0 END),0) AS receivedVolume,
            COALESCE(SUM(CASE WHEN p.status IN ('undelivered','returned') THEN 1 ELSE 0 END),0) AS stockTracking,
            COALESCE(SUM(CASE WHEN p.status IN ('undelivered','returned') THEN p.weight ELSE 0 END),0) AS stockWeight,
            COALESCE(SUM(CASE WHEN p.status IN ('undelivered','returned') THEN p.volume ELSE 0 END),0) AS stockVolume
            FROM account a LEFT JOIN c_parcel p ON p.account_id=a.id AND p.deleted=0
            WHERE $where GROUP BY a.id,a.name,a.c_name ORDER BY a.name,a.id LIMIT $limit OFFSET $offset";
        $stmt=$this->em->getPDO()->prepare($sql);
        $stmt->execute(array_merge([$from,$to,$from,$to,$from,$to],$bind));
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            foreach (['totalTracking','receivedTracking','stockTracking'] as $key) { $row[$key]=(int)$row[$key]; }
            foreach (['receivedWeight','receivedVolume','stockWeight','stockVolume'] as $key) { $row[$key]=(float)$row[$key]; }
        }
        return ['list'=>$rows,'total'=>$total,'offset'=>$offset,'limit'=>$limit];
    }

    public function tracking(array $params, bool $export=false): array
    {
        $id=(string)($params['accountId']??'');
        $this->access->account($id,'CInventory');
        if ($export) { $this->access->account($id,'CInventory','export'); }
        [$from,$to]=$this->dates($params);
        $where='account_id=? AND deleted=0'; $bind=[$id];
        if (($params['receivedOnly']??'')==='true') { $where.=' AND created_at >= ? AND created_at < ?'; $bind[]=$from; $bind[]=$to; }
        $stmt=$this->em->getPDO()->prepare('SELECT COUNT(*) FROM c_parcel WHERE '.$where);
        $stmt->execute($bind); $total=(int)$stmt->fetchColumn();
        $limit=$export?10000:100; $offset=max(0,(int)($params['offset']??0));
        if ($export && $total>$limit) { throw new BadRequest('Trên 10.000 tracking. Hãy lọc theo ngày nhập trước khi xuất.'); }
        $stmt=$this->em->getPDO()->prepare('SELECT id,name,package_count AS packageCount,weight,volume,status,
            created_at AS receivedAt,delivered_at AS deliveredAt,returned_at AS returnedAt
            FROM c_parcel WHERE '.$where." ORDER BY created_at DESC,id LIMIT $limit OFFSET $offset");
        $stmt->execute($bind);
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            foreach (['receivedAt','deliveredAt','returnedAt'] as $field) {
                if ($row[$field]) {
                    $row[$field]=(new DateTimeImmutable($row[$field],new DateTimeZone('UTC')))
                        ->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->format('Y-m-d H:i:s');
                }
            }
        }
        return ['list'=>$rows,'total'=>$total,'offset'=>$offset,'limit'=>$limit];
    }

    private function dates(array $params): array
    {
        $zone=new DateTimeZone('Asia/Ho_Chi_Minh');
        $values=[];
        foreach (['from','to'] as $key) {
            $text=(string)($params[$key]??'');
            if ($text==='') { $values[$key]=$key==='from'?'1970-01-01':'2099-12-31'; continue; }
            $date=DateTimeImmutable::createFromFormat('!Y-m-d',$text,$zone);
            if (!$date || $date->format('Y-m-d')!==$text) { throw new BadRequest('Ngày lọc không hợp lệ.'); }
            $values[$key]=$text;
        }
        if ($values['from']>$values['to']) { throw new BadRequest('Ngày bắt đầu phải trước ngày kết thúc.'); }
        return [(new DateTimeImmutable($values['from'],$zone))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            (new DateTimeImmutable($values['to'],$zone))->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')];
    }
}
