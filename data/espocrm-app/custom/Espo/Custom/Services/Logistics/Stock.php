<?php
namespace Espo\Custom\Services\Logistics;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

final class Stock
{
    public function __construct(private EntityManager $em) {}

    public function move(Entity $parcel, string $status, string $kind, ?string $shipmentId, string $reason = ''): void
    {
        if ($parcel->get('status') === $status) { return; }
        $parcel->set('status', $status);
        if ($status === 'delivered') { $parcel->set('deliveredAt', gmdate('Y-m-d H:i:s')); }
        if ($status === 'returned') { $parcel->set('returnedAt', gmdate('Y-m-d H:i:s')); }
        $this->em->saveEntity($parcel, ['logisticsInternal' => true, 'silent' => true, 'skipHooks' => true]);
        $event = $this->em->getNewEntity('CStockEvent');
        $event->set(['name' => $kind . ' ' . $parcel->get('name'), 'kind' => $kind,
            'accountId' => $parcel->get('accountId'), 'parcelId' => $parcel->getId(),
            'shipmentId' => $shipmentId, 'reason' => $reason]);
        $this->em->saveEntity($event, ['logisticsInternal' => true]);
    }

    public function paidShipment(Entity $shipment): void
    {
        if (!$shipment->get('paid')) { return; }
        foreach ($this->em->getRepository('CParcel')->where(['shipmentId' => $shipment->getId()])->find() as $parcel) {
            $this->move($parcel, 'delivered', 'delivered', $shipment->getId());
        }
    }
}
