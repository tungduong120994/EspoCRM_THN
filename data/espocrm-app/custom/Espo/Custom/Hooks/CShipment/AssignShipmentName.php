<?php

namespace Espo\Custom\Hooks\CShipment;

use Espo\Core\Container;
use Espo\ORM\Entity;

class AssignShipmentName
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function beforeSave(Entity $entity): void
    {
        if ($entity->get('name')) {
            return;
        }

        $entityManager = $this->container->get('entityManager');
        $name = $this->buildShipmentName($entity, $entityManager);
        
        if ($name) {
            $entity->set('name', $name);
        }
    }

    private function buildShipmentName(Entity $shipment, $entityManager): ?string
    {
        $parts = [];

        $customerLabel = $this->resolveCustomerLabel($shipment, $entityManager);
        if ($customerLabel) {
            $parts[] = $customerLabel;
        }

        $shipmentDateTime = $this->resolveShipmentDateTime($shipment);
        $parts[] = sprintf(
            'Shipment %s %s',
            $shipmentDateTime->format('Y-m-d'),
            $shipmentDateTime->format('H:i')
        );

        if (!$parts) {
            return null;
        }

        return implode(' - ', $parts);
    }

    private function resolveShipmentDateTime(Entity $shipment): \DateTimeImmutable
    {
        $createdAt = $shipment->get('createdAt');
        if ($createdAt) {
            return new \DateTimeImmutable($createdAt);
        }

        return new \DateTimeImmutable('now');
    }

    private function resolveCustomerLabel(Entity $shipment, $entityManager): ?string
    {
        $accountId = $shipment->get('accountId');
        if (!$accountId) {
            return null;
        }

        $account = $entityManager->getEntity('Account', $accountId);
        if (!$account) {
            return null;
        }

        return $account->get('cID');
    }
}
