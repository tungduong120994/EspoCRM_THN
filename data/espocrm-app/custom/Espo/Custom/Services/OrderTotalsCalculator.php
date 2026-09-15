<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class OrderTotalsCalculator
{
    public function __construct(private EntityManager $entityManager)
    {
    }

    public function apply(Entity $order): void
    {
        $orderId = $order->getId();
        if (!$orderId) {
            return;
        }

        $items = $this->entityManager
            ->getRepository('COrderItem')
            ->where(['orderId' => $orderId])
            ->find();

        $totals = $this->aggregateItems($items);
        $this->applyItemTotals($order, $totals);
        $this->applyOrderTotals($order, $totals);

        $remainingAmountVnd = $this->computeRemainingAmountVnd($order->get('grandTotalPriceVnd'), $order->get('depositAmountVnd'));
        $this->applyRemainingAmount($order, $remainingAmountVnd);

        $totalPayableAmountVnd = $this->computeTotalPayableAmountVnd($order);
        $order->set('totalPayableAmountVnd', $totalPayableAmountVnd);
    }

    private function computeTotalPayableAmountVnd(Entity $order): ?float
    {
        $remainingAmountVnd = (float) ($order->get('remainingAmountVnd') ?? 0.0);
        return $remainingAmountVnd ?: null;
    }

    private function aggregateItems(iterable $items): array
    {
        $totals = [
            'totalItemsPriceCny' => 0.0,
            'totalQuantity' => 0,
        ];

        foreach ($items as $item) {
            $totals['totalItemsPriceCny'] += (float) ($item->get('totalPriceCny') ?? 0);
            $totals['totalQuantity'] += (int) ($item->get('quantity') ?? 0);
        }

        return $totals;
    }

    private function applyItemTotals(Entity $order, array $totals): void
    {
        $order->set('totalItemsPriceCny', $totals['totalItemsPriceCny']);
        $order->set('totalQuantity', $totals['totalQuantity']);
    }

    private function applyOrderTotals(Entity $order, array $totals): void
    {
        $shippingFee = (float) ($order->get('domesticShippingFeeCny') ?? 0);
        $totalOrderValueCny = $totals['totalItemsPriceCny'] + $shippingFee;
        $order->set('totalOrderValueCny', $totalOrderValueCny);

        $exchangeRate = (float) ($order->get('exchangeRate') ?? 0);
        $totalOrderValueVnd = $this->convertToVnd($totalOrderValueCny, $exchangeRate);
        $order->set('totalOrderValueVnd', $totalOrderValueVnd);

        $serviceFeePercent = (float) ($order->get('serviceFeeVndPercent') ?? 0);
        // The existing fraction remains unchanged for historical records. The new
        // optional input uses whole percent (1.5 means 1.5%, not 150%).
        if ($order->get('serviceFeeRatePercent') !== null) {
            $rate = $order->get('serviceFeeRatePercent');
            \Espo\Custom\Services\Logistics\Money::percent(0, $rate);
            $serviceFeePercent = (float) $rate / 100;
            $order->set('serviceFeeVndPercent', $serviceFeePercent);
        }
        $order->set(
            'serviceFeeVnd',
            $this->computeServiceFeeVnd($totalOrderValueVnd, $serviceFeePercent)
        );

        $estimatedChinaVietnamShippingFeeTotalPriceVnd = $this->computeEstimatedShippingVnd(
            (float) ($order->get('estimatedChinaVietnamShippingWeightKg') ?? 0),
            (float) ($order->get('estimatedChinaVietnamShippingUnitPriceVnd') ?? 0)
        );
        $order->set('estimatedChinaVietnamShippingFeeTotalPriceVnd', $estimatedChinaVietnamShippingFeeTotalPriceVnd);

        $additionalAmountVnd = (float) ($order->get('additionalAmountVnd') ?? 0);
        $order->set(
            'grandTotalPriceVnd',
            $this->computeGrandTotalVnd(
                $totalOrderValueVnd,
                $order->get('serviceFeeVnd'),
                $estimatedChinaVietnamShippingFeeTotalPriceVnd,
                $additionalAmountVnd
            )
        );
        $declaredFees = \Espo\Custom\Services\Logistics\OrderFees::apply($order);
        if ($order->get('formalImport')) {
            $order->set('grandTotalPriceVnd', (float) $order->get('grandTotalPriceVnd') + $declaredFees);
        } else {
            foreach (['entrustmentVnd', 'importTaxVnd', 'vatVnd', 'declaredFeesVnd'] as $field) {
                $order->set($field, 0.0);
            }
        }
    }

    private function convertToVnd(?float $totalOrderValueCny, float $exchangeRate): ?float
    {
        $totalOrderValueCny = (float) ($totalOrderValueCny ?? 0.0);

        if ($totalOrderValueCny === 0.0 || $exchangeRate === 0.0) {
            return null;
        }

        return $totalOrderValueCny * $exchangeRate;
    }

    private function computeServiceFeeVnd(?float $totalOrderValueVnd, float $serviceFeePercent): ?float
    {
        $totalOrderValueVnd = (float) ($totalOrderValueVnd ?? 0.0);

        if ($totalOrderValueVnd === 0.0 || $serviceFeePercent === 0.0) {
            return null;
        }

        return $totalOrderValueVnd * $serviceFeePercent;
    }

    private function computeEstimatedShippingVnd(float $kg, float $pricePerKg): ?float
    {
        if ($kg === 0.0 || $pricePerKg === 0.0) {
            return null;
        }

        return $kg * $pricePerKg;
    }

    private function computeGrandTotalVnd(?float $orderValueVnd, ?float $serviceFeeVnd, ?float $shippingVnd = null, ?float $additionalAmountVnd = null): ?float
    {
        $components = array_filter([
            $orderValueVnd,
            $serviceFeeVnd,
            $shippingVnd,
            $additionalAmountVnd,
        ], static fn ($value) => $value !== null);

        if (!$components) {
            return null;
        }

        return array_sum($components);
    }

    private function computeRemainingAmountVnd(?float $grandTotalVnd, ?float $depositAmount): ?float
    {
        if ($grandTotalVnd === null) {
            return null;
        }

        if (!$depositAmount) {
            return $grandTotalVnd;
        }

        return $grandTotalVnd - $depositAmount;
    }

    private function applyRemainingAmount(Entity $order, ?float $remainingAmountVnd): void
    {
        $order->set('remainingAmountVnd', $remainingAmountVnd);
    }
}
