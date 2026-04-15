<?php

namespace Espo\Custom\Hooks\CParcel;

use Espo\ORM\Entity;
use Espo\Core\Di\EntityManagerAware;
use Espo\Custom\Services\ShipmentTotalsCalculator;

class RecalculateParcel implements EntityManagerAware
{
    use \Espo\Core\Di\EntityManagerSetter;

    private ?ShipmentTotalsCalculator $calculator = null;

    public function __construct(ShipmentTotalsCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    public function beforeSave(Entity $entity, array $options): void
    {
        if (!empty($options['silent']) || !empty($options['skipHooks'])) {
            return;
        }

        $this->calculateTotalDeliveryPrice($entity);
    }

    public function afterSave(Entity $entity, array $options): void
    {
        if (!empty($options['silent']) || !empty($options['skipHooks'])) {
            return;
        }

        $this->updateShipment($entity);
    }

    public function afterRemove(Entity $entity, array $options): void
    {
        if (!empty($options['silent']) || !empty($options['skipHooks'])) {
            return;
        }

        $this->updateShipment($entity);
    }

    private function calculateTotalDeliveryPrice(Entity $entity): void
    {
        $weight = $entity->get('weight');
        $volume = $entity->get('volume');
        $shipmentId = $entity->get('shipmentId');
        
        if (!$shipmentId) {
            $entity->set('totalDeliveryPriceVnd', 0);
            return;
        }

        $shipment = $this->entityManager->getEntity('CShipment', $shipmentId);
        if (!$shipment) {
            $entity->set('totalDeliveryPriceVnd', 0);
            return;
        }

        $unitPrice = (float) ($shipment->get('unitDeliveryPriceVnd') ?? 0);

        // Use weight or volume (weight has priority)
        $multiplier = 0;
        if ($weight !== null && $weight > 0) {
            $multiplier = $weight;
        } elseif ($volume !== null && $volume > 0) {
            $multiplier = $volume;
        }

        $totalDeliveryPriceVnd = $multiplier * $unitPrice;
        $entity->set('totalDeliveryPriceVnd', $totalDeliveryPriceVnd);
    }

    private function updateShipment(Entity $parcel): void
    {
        $shipmentId = $parcel->get('shipmentId');
        if (!$shipmentId) {
            return;
        }

        $shipment = $this->entityManager->getEntity('CShipment', $shipmentId);
        if (!$shipment) {
            return;
        }

        $this->calculator->apply($shipment);
        $this->entityManager->saveEntity($shipment, ['silent' => true]);
    }
}
