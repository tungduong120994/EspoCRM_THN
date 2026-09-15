<?php
namespace Espo\Custom\Services\Logistics;

use Espo\Core\Acl;
use Espo\Entities\User;
use Espo\ORM\EntityManager;
use Espo\ORM\Entity;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;

final class Access
{
    public function __construct(private Acl $acl, private User $user, private EntityManager $em) {}

    public function level(string $scope, string $action = 'read'): string
    {
        if ($this->user->isAdmin()) { return 'all'; }
        if (!$this->acl->checkScope($scope, $action)) { throw new Forbidden(); }
        $level = $this->acl->getLevel($scope, $action);
        if (!in_array($level, ['all', 'own'], true)) { throw new Forbidden(); }
        return $level;
    }

    public function account(string $id, string $scope, string $action = 'read'): Entity
    {
        $level = $this->level($scope, $action);
        $account = $this->em->getEntity('Account', $id);
        if (!$account || $account->get('deleted')) { throw new NotFound(); }
        if ($level === 'own' && $account->get('assignedUserId') !== $this->user->getId()) {
            throw new Forbidden();
        }
        return $account;
    }

    public function ownId(): string { return $this->user->getId(); }

    public function canRead(Entity $entity): bool { return $this->acl->check($entity, 'read'); }

    public function canEditFinance(string $accountId): bool
    {
        try { $this->account($accountId, 'CFinance', 'edit'); return true; }
        catch (Forbidden $e) { return false; }
    }

    public function shipment(Entity $shipment, string $action = 'edit'): void
    {
        if (!$this->acl->check($shipment, $action)) { throw new Forbidden(); }
    }
}
