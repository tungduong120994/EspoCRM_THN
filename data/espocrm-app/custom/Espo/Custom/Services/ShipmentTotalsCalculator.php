<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class ShipmentTotalsCalculator
{
    public function __construct(private EntityManager $entityManager)
    {
    }

    public function apply(Entity $shipment): void
    {
        $shipmentId = $shipment->getId();
        if (!$shipmentId) {
            return;
        }

        $parcels = $this->getParcels($shipmentId);
        $totals = $this->aggregateParcels($parcels);
        $this->applyTotals($shipment, $totals);
        
        $totalDeliveryPriceVnd = $this->calculateTotalDeliveryPrice($shipment, $totals);
        $this->setTotalDeliveryPrice($shipment, $totalDeliveryPriceVnd);
        
        $orderRemainingAmountVnd = $this->getOrderRemainingAmount($shipment);
        $this->setOrderRemainingAmount($shipment, $orderRemainingAmountVnd);
        
        $totalPayableAmountVnd = $this->calculateTotalPayableAmount($shipment);
        $this->setTotalPayableAmount($shipment, $totalPayableAmountVnd);
    }

    private function getParcels(string $shipmentId): iterable
    {
        return $this->entityManager
            ->getRepository('CParcel')
            ->where(['shipmentId' => $shipmentId])
            ->find();
    }

    private function aggregateParcels(iterable $parcels): array
    {
        $totals = [
            'totalPackages' => 0,
            'totalWeight' => 0.0,
            'totalVolume' => 0.0,
        ];

        foreach ($parcels as $parcel) {
            $totals['totalPackages'] += (int) ($parcel->get('packageCount') ?? 0);
            $totals['totalWeight'] += (float) ($parcel->get('weight') ?? 0);
            $totals['totalVolume'] += (float) ($parcel->get('volume') ?? 0);
        }

        return $totals;
    }

    private function applyTotals(Entity $shipment, array $totals): void
    {
        $shipment->set('totalPackages', $totals['totalPackages']);
        $shipment->set('totalWeight', $totals['totalWeight']);
        $shipment->set('totalVolume', $totals['totalVolume']);
    }

    private function setOrderRemainingAmount(Entity $shipment, float $orderRemainingAmountVnd): void
    {
        $shipment->set('orderRemainingAmountVnd', $orderRemainingAmountVnd);
    }

    private function getOrderRemainingAmount(Entity $shipment): float
    {
        $orderId = $shipment->get('orderId');
        if (!$orderId) {
            return 0.0;
        }

        $order = $this->entityManager->getEntity('COrder', $orderId);
        if (!$order) {
            return 0.0;
        }

        return (float) ($order->get('remainingAmountVnd') ?? 0);
    }

    private function calculateTotalPayableAmount(Entity $shipment): float
    {
        $orderRemainingAmountVnd = (float) ($shipment->get('orderRemainingAmountVnd') ?? 0);
        $additionalAmountVnd = (float) ($shipment->get('additionalAmountVnd') ?? 0);
        $totalDeliveryPrice = (float) ($shipment->get('totalDeliveryPriceVnd') ?? 0);
        return $orderRemainingAmountVnd + $additionalAmountVnd + $totalDeliveryPrice;
    }

    private function calculateTotalDeliveryPrice(Entity $shipment, array $totals): float
    {
        $totalWeight = (float) ($totals['totalWeight'] ?? 0.0);
        $totalVolume = (float) ($totals['totalVolume'] ?? 0.0);
        $unitDeliveryPriceVnd = (float) ($shipment->get('unitDeliveryPriceVnd') ?? 0);
        
        return ($totalWeight + $totalVolume) * $unitDeliveryPriceVnd;
    }

    private function setTotalDeliveryPrice(Entity $shipment, float $totalDeliveryPriceVnd): void
    {
        $shipment->set('totalDeliveryPriceVnd', $totalDeliveryPriceVnd);
    }

    private function setTotalPayableAmount(Entity $shipment, float $totalPayableAmountVnd): void
    {
        $shipment->set('totalPayableAmountVnd', $totalPayableAmountVnd);
    }
}
