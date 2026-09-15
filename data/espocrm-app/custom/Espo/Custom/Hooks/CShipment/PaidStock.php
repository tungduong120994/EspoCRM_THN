<?php
namespace Espo\Custom\Hooks\CShipment;

use Espo\ORM\Entity;
use Espo\Custom\Services\Logistics\Stock;

class PaidStock
{
    public static int $order=90;
    public function __construct(private Stock $stock) {}
    public function afterSave(Entity $entity, array $options=[]): void
    {
        if (!empty($options['silent']) || !empty($options['skipHooks'])) { return; }
        if ($entity->get('paid') && $entity->isAttributeChanged('paid')) { $this->stock->paidShipment($entity); }
    }
}
