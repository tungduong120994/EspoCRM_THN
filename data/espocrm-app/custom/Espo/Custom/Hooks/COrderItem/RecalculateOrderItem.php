<?php

namespace Espo\Custom\Hooks\COrderItem;

use Espo\Core\Container;
use Espo\Core\Exceptions\Error;
use Espo\ORM\Entity;

class RecalculateOrderItem
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function beforeSave(Entity $entity): void
    {
        $productId = $entity->get('productId');
        $entityManager = $this->container->get('entityManager');
        if (!$productId) {
            throw new Error('A product must be selected before saving the order item.');
        }
        $product = $entityManager->getEntity('CProduct', $productId);
        if (!$product) {
            throw new Error('The selected product no longer exists.');
        }

        $unitPriceCny = $product->get('unitPriceCny');
        $entity->set('unitPriceCny', $unitPriceCny);

        $totalPriceCny = $this->computeTotalPriceCny($entity);
        $entity->set('totalPriceCny', $totalPriceCny);

        $name = $this->computeName($entity);
        $entity->set('name', $name);
    }

    private function computeTotalPriceCny(Entity $orderItem): float | null
    {
        $quantity = (float) ($orderItem->get('quantity') ?? 0);
        $unitPriceCny = (float) ($orderItem->get('unitPriceCny') ?? 0);

        if ($quantity === 0 || $unitPriceCny === 0) {
            return null;
        }

        return $quantity * $unitPriceCny;
    }

    private function computeName(Entity $orderItem): ?string
    {
        $entityManager = $this->container->get('entityManager');

        $productName = $orderItem->get('productName');
        
        $orderId = $orderItem->get('orderId');
        $order = $entityManager->getEntity('COrder', $orderId);
        $orderName = $order->get('name');

        $parts = array_filter([$productName, $orderName], fn($part) => !empty($part));

        return implode(' - ', $parts);
    }
}