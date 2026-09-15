<?php

namespace Espo\Custom\Hooks\CShipment;

use Espo\ORM\Entity;
use Espo\Core\Di\EntityManagerAware;
use Espo\Custom\Services\ShipmentTotalsCalculator;

class RecalculateTotals implements EntityManagerAware
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

        $this->calculator->apply($entity);
    }
}
