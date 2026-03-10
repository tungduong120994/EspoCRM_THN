<?php

namespace Espo\Custom\Hooks\Account;

use Espo\Core\Exceptions\Error;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class AutoIncrementName
{
    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function beforeSave(Entity $entity, array $options): void
    {
        // Ne générer le name que si c'est une nouvelle entité et que name n'est pas déjà défini
        if (!$entity->isNew()) {
            return;
        }

        if ($entity->get('name')) {
            return;
        }

        $newName = $this->generateNextName();
        $entity->set('name', $newName);
    }

    private function generateNextName(): string
    {
        // Récupérer le dernier compte par ordre de création
        $lastAccount = $this->entityManager
            ->getRDBRepository('Account')
            ->select(['name'])
            ->where([
                'name!=' => null,
                'name!=' => ''
            ])
            ->order('createdAt', 'DESC')
            ->findOne();

        if (!$lastAccount || !$lastAccount->get('name')) {
            // Pas de compte existant, commencer à THN01
            return 'THN01';
        }

        $lastName = $lastAccount->get('name');
        
        // Extraire le numéro du name (ex: THN01 -> 01, THN1234 -> 1234)
        if (preg_match('/^THN(\d+)$/', $lastName, $matches)) {
            $lastNumber = (int)$matches[1];
            $nextNumber = $lastNumber + 1;
            
            // Garder le même nombre de chiffres avec padding si nécessaire
            $numberLength = strlen($matches[1]);
            $nextNumberStr = str_pad($nextNumber, $numberLength, '0', STR_PAD_LEFT);
            
            return 'THN' . $nextNumberStr;
        }
        
        // Si le format n'est pas reconnu, retourner THN01
        return 'THN01';
    }
}
