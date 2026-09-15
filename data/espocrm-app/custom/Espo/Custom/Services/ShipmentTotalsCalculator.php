<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Core\Exceptions\BadRequest;

class ShipmentTotalsCalculator
{
    public function __construct(private EntityManager $entityManager)
    {
    }

    public function apply(Entity $shipment): void
    {
        if (in_array($shipment->get('workflowStatus'), ['confirmed', 'cancelled'], true)) {
            return; // Posted amounts and measurements are immutable snapshots.
        }
        $this->validatePricing($shipment);
        $shipmentId = $shipment->getId();
        if (!$shipmentId) {
            return;
        }

        $parcels = [];
        foreach ($this->getParcels($shipmentId) as $parcel) {
            $parcels[] = $parcel;
        }
        $totals = $this->aggregateParcels($parcels);
        $this->applyTotals($shipment, $totals);
        
        $totalDeliveryPriceVnd = $this->calculateTotalDeliveryPrice($shipment, $totals);
        $this->setTotalDeliveryPrice($shipment, $totalDeliveryPriceVnd);

        // In automatic mode every parcel follows the winning basis of the WHOLE
        // shipment. Recalculate siblings too when a measurement changes that basis.
        foreach ($parcels as $parcel) {
            $price = $this->calculateParcelPrice($parcel, $shipment);
            if ($parcel->get('totalDeliveryPriceVnd') === null ||
                (float) $parcel->get('totalDeliveryPriceVnd') !== $price) {
                $parcel->set('totalDeliveryPriceVnd', $price);
                $this->entityManager->saveEntity($parcel, ['silent' => true, 'skipHooks' => true]);
            }
        }
        
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
        $ids = $shipment->get('additionalOrders') ?? [];
        if (!is_array($ids)) { throw new BadRequest('Danh sách đơn hàng không hợp lệ.'); }
        if ($shipment->get('orderId')) { $ids[] = $shipment->get('orderId'); }
        $total = 0.0;
        foreach (array_unique($ids) as $id) {
            $order = $this->entityManager->getEntity('COrder', $id);
            if (!$order) { continue; }
            if ($shipment->get('accountId') && $order->get('accountId') !== $shipment->get('accountId')) {
                throw new BadRequest('Các đơn trên phiếu phải thuộc cùng khách hàng.');
            }
            $total += (float) ($order->get('remainingAmountVnd') ?? 0);
        }
        return $total;
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
        
        $mode = $shipment->get('deliveryPricingMode');
        if (!$mode) {
            // Existing shipments are not silently migrated to a new tariff.
            $shipment->set('appliedDeliveryPricingBasis', null);
            $shipment->set('deliveryPriceByWeightVnd', null);
            $shipment->set('deliveryPriceByVolumeVnd', null);
            return ($totalWeight + $totalVolume) * $unitDeliveryPriceVnd;
        }

        $weightRate = $shipment->get('unitDeliveryPricePerKgVnd');
        $volumeRate = $shipment->get('unitDeliveryPricePerM3Vnd');
        $weightPrice = $weightRate === null ? null : $totalWeight * (float) $weightRate;
        $volumePrice = $volumeRate === null ? null : $totalVolume * (float) $volumeRate;
        $shipment->set('deliveryPriceByWeightVnd', $weightPrice);
        $shipment->set('deliveryPriceByVolumeVnd', $volumePrice);

        $basis = $mode === 'higher'
            ? ($weightPrice >= $volumePrice ? 'weight' : 'volume')
            : $mode;
        $shipment->set('appliedDeliveryPricingBasis', $basis);

        return (float) ($basis === 'weight' ? $weightPrice : $volumePrice);
    }

    public function validatePricing(Entity $shipment): void
    {
        $mode = $shipment->get('deliveryPricingMode');
        if (!$mode) {
            return;
        }
        if (!in_array($mode, ['weight', 'volume', 'higher'], true)) {
            throw new BadRequest('Invalid shipment delivery pricing mode.');
        }
        $required = $mode === 'weight' ? ['unitDeliveryPricePerKgVnd']
            : ($mode === 'volume' ? ['unitDeliveryPricePerM3Vnd']
                : ['unitDeliveryPricePerKgVnd', 'unitDeliveryPricePerM3Vnd']);
        foreach ($required as $field) {
            $value = $shipment->get($field);
            if ($value === null || $value === '' || !is_numeric($value) ||
                !is_finite((float) $value) || (float) $value < 0) {
                throw new BadRequest('A non-negative delivery rate is required: ' . $field);
            }
        }
    }

    public function calculateParcelPrice(Entity $parcel, Entity $shipment): float
    {
        $weight = (float) ($parcel->get('weight') ?? 0);
        $volume = (float) ($parcel->get('volume') ?? 0);
        if (!$shipment->get('deliveryPricingMode')) {
            return ($weight > 0 ? $weight : max(0, $volume)) *
                (float) ($shipment->get('unitDeliveryPriceVnd') ?? 0);
        }
        $basis = $shipment->get('deliveryPricingMode') === 'higher'
            ? $shipment->get('appliedDeliveryPricingBasis')
            : $shipment->get('deliveryPricingMode');

        return $basis === 'weight'
            ? $weight * (float) $shipment->get('unitDeliveryPricePerKgVnd')
            : $volume * (float) $shipment->get('unitDeliveryPricePerM3Vnd');
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
