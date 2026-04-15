<?php

namespace Espo\Custom\Hooks\COrderItem;

use Espo\Core\Container;
use Espo\Core\Exceptions\Error;
use Espo\Custom\Services\OrderTotalsCalculator;
use Espo\ORM\Entity;

class RecalculateOrder
{
    protected Container $container;
    private ?OrderTotalsCalculator $totalsCalculator = null;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function beforeSave(Entity $entity): void
    {
        $this->recalculate($entity);
    }

    public function afterSave(Entity $entity): void
    {
        $this->recalculate($entity);
    }

    public function afterRemove(Entity $entity): void
    {
        $this->recalculate($entity);
    }

    private function recalculate(Entity $entity): void
    {
        $entityManager = $this->container->get('entityManager');

        $order = $this->resolveOrderEntity($entity, $entityManager);
        if (!$order) {
            throw new Error('Unable to determine the order associated with this update.');
        }

        $this->assignOrderName($order, $entityManager);

        $orderId = $order->getId();
        if (!$orderId) {
            // Order not persisted yet; nothing to aggregate.
            return;
        }

        $this->getTotalsCalculator()->apply($order);

        if ($entity->getEntityType() !== 'COrder') {
            $entityManager->saveEntity($order);
        }
    }

    private function resolveOrderEntity(Entity $entity, $entityManager): ?Entity
    {
        if ($entity->getEntityType() === 'COrder') {
            return $entity;
        }

        $orderId = $entity->get('orderId');
        if (!$orderId) {
            return null;
        }

        return $entityManager->getEntity('COrder', $orderId);
    }

    private function assignOrderName(Entity $order, $entityManager): void
    {
        if($order->get('name')) {
            return;
        }
        
        $name = $this->buildOrderName($order, $entityManager);
        if ($name) {
            $order->set('name', $name);
        }
    }

    private function buildOrderName(Entity $order, $entityManager): ?string
    {
        $parts = [];

        $customerLabel = $this->resolveCustomerLabel($order, $entityManager);
        if ($customerLabel) {
            $parts[] = $customerLabel;
        }

        $orderDateTime = $this->resolveOrderDateTime($order);
        $parts[] = sprintf(
            'Order %s %s',
            $orderDateTime->format('Y-m-d'),
            $orderDateTime->format('H:i')
        );

        if (!$parts) {
            return null;
        }

        return implode(' - ', $parts);
    }

    private function resolveOrderDateTime(Entity $order): \DateTimeImmutable
    {
        $createdAt = $order->get('createdAt');
        if ($createdAt) {
            return new \DateTimeImmutable($createdAt);
        }

        return new \DateTimeImmutable('now');
    }

    private function resolveCustomerLabel(Entity $order, $entityManager): ?string
    {
        $accountId = $order->get('accountId');
        $account = $entityManager->getEntity('Account', $accountId);
        return $account->get('cID');
    }


    private function getTotalsCalculator(): OrderTotalsCalculator
    {
        if ($this->totalsCalculator === null) {
            $this->totalsCalculator = new OrderTotalsCalculator(
                $this->container->get('entityManager')
            );
        }

        return $this->totalsCalculator;
    }
}
