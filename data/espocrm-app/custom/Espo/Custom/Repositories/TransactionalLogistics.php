<?php
namespace Espo\Custom\Repositories;

use Espo\ORM\Entity;

/** Keep normal form/API saves atomic with repricing and stock hooks. */
class TransactionalLogistics extends \Espo\Core\Repositories\Database
{
    public function save(Entity $entity, array $options=[]): void
    {
        $this->entityManager->getTransactionManager()->run(function () use ($entity,$options) {
            $this->lockCustomers($entity);
            parent::save($entity,$options);
        });
    }

    public function remove(Entity $entity, array $options=[]): void
    {
        $this->entityManager->getTransactionManager()->run(function () use ($entity,$options) {
            $this->lockCustomers($entity);
            parent::remove($entity,$options);
        });
    }

    private function lockCustomers(Entity $entity): void
    {
        $ids=array_filter([$entity->get('accountId'),$entity->getFetched('accountId')]);
        if ($entity->getEntityType()==='COrderItem') {
            foreach (array_filter([$entity->get('orderId'),$entity->getFetched('orderId')]) as $id) {
                $o=$this->entityManager->getEntity('COrder',$id);
                if ($o && $o->get('accountId')) { $ids[]=$o->get('accountId'); }
            }
        }
        $ids=array_unique($ids); sort($ids);
        foreach ($ids as $id) {
            $q=$this->entityManager->getPDO()->prepare('SELECT id FROM account WHERE id=? FOR UPDATE');
            $q->execute([$id]); $q->fetchColumn();
        }
    }
}
