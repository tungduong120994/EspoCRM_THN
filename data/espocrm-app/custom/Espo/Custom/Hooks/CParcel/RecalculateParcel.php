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

        $this->calculator->validatePricing($shipment);
        $entity->set('totalDeliveryPriceVnd', $this->calculator->calculateParcelPrice($entity, $shipment));
    }

    private function updateShipment(Entity $parcel): void
    {
        // Moving/unlinking a parcel affects both the previous and new shipment.
        $ids = array_unique(array_filter([
            $parcel->get('shipmentId'),
            $parcel->hasFetched('shipmentId') ? $parcel->getFetched('shipmentId') : null,
        ]));
        foreach ($ids as $shipmentId) {
            $shipment = $this->entityManager->getEntity('CShipment', $shipmentId);
            if (!$shipment) {
                continue;
            }
            $this->calculator->apply($shipment);
            $this->entityManager->saveEntity($shipment, ['silent' => true, 'skipHooks' => true]);
            if ($shipmentId === $parcel->get('shipmentId')) {
                // Keep the API response in sync with the recalculated stored price.
                $parcel->set('totalDeliveryPriceVnd', $this->calculator->calculateParcelPrice($parcel, $shipment));
            }
        }
    }
}
