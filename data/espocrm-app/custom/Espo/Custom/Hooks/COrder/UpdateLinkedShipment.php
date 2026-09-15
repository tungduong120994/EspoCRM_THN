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

        $shipments = $shipment ? [$shipment->getId() => $shipment] : [];
        // Additional orders are stored on the shipment, not on the legacy
        // one-to-one order relation. Refresh every affected draft as well.
        if ($entity->get('accountId')) {
            foreach ($this->entityManager->getRepository('CShipment')
                ->where(['accountId' => $entity->get('accountId')])->find() as $candidate) {
                if (in_array($entity->getId(), $candidate->get('additionalOrders') ?? [], true)) {
                    $shipments[$candidate->getId()] = $candidate;
                }
            }
        }
        foreach ($shipments as $linked) {
            if (in_array($linked->get('workflowStatus'), ['confirmed', 'cancelled'], true)) {
                continue;
            }
            $this->calculator->apply($linked);
            $this->entityManager->saveEntity($linked, ['silent' => true]);
        }
    }
}
