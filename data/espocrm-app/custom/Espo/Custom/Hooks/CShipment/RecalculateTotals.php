<?php

namespace Espo\Custom\Hooks\CShipment;

use Espo\ORM\Entity;
use Espo\Core\Di\EntityManagerAware;
use Espo\Custom\Services\ShipmentTotalsCalculator;

class RecalculateTotals implements EntityManagerAware
{
    use \Espo\Core\Di\EntityManagerSetter;

    private ?ShipmentTotalsCalculator $calculator = null;

    public function __construct(ShipmentTotalsCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    public function beforeSave(Entity $entity, array $options): void
    {
        if (!empty($options['silent'])) {
            return;
        }

        $this->updateParcels($entity);
        $this->calculator->apply($entity);
    }

    private function updateParcels(Entity $shipment): void
    {
        $shipmentId = $shipment->getId();
        if (!$shipmentId) {
            return;
        }

        $unitDeliveryPriceVnd = (float) ($shipment->get('unitDeliveryPriceVnd') ?? 0);
        
        $parcels = $this->entityManager
            ->getRepository('CParcel')
            ->where(['shipmentId' => $shipmentId])
            ->find();

        foreach ($parcels as $parcel) {
            $weight = $parcel->get('weight');
            $volume = $parcel->get('volume');

            $multiplier = 0;
            if ($weight !== null && $weight > 0) {
                $multiplier = $weight;
            } elseif ($volume !== null && $volume > 0) {
                $multiplier = $volume;
            }

            $totalDeliveryPriceVnd = $multiplier * $unitDeliveryPriceVnd;
            $parcel->set('totalDeliveryPriceVnd', $totalDeliveryPriceVnd);
            
            $this->entityManager->saveEntity($parcel, ['silent' => true, 'skipHooks' => true]);
        }
    }
}
