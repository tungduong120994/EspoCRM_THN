<?php

namespace Espo\Custom\Hooks\COrder;

use Espo\ORM\Entity;
use Espo\Core\Di\EntityManagerAware;
use Espo\Custom\Services\ShipmentTotalsCalculator;

class UpdateLinkedShipment implements EntityManagerAware
{
    use \Espo\Core\Di\EntityManagerSetter;

    private ?ShipmentTotalsCalculator $calculator = null;

    public function __construct(ShipmentTotalsCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    public function afterSave(Entity $entity, array $options): void
    {
        // If saving from hook itself, skip to prevent loops
        if (!empty($options['silent'])) {
            return;
        }

        // Get linked shipment
        $shipment = $this->entityManager
            ->getRDBRepository('COrder')
            ->getRelation($entity, 'shipment')
            ->findOne();

        if (!$shipment) {
            return;
        }

        $this->calculator->apply($shipment);
        $this->entityManager->saveEntity($shipment, ['silent' => true]);
    }
}
