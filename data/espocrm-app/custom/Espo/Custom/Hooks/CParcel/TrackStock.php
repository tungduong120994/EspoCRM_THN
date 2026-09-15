<?php
namespace Espo\Custom\Hooks\CParcel;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Custom\Services\Logistics\Stock;

class TrackStock
{
    public static int $order=90;
    public function __construct(private EntityManager $em, private Stock $stock) {}
    public function afterSave(Entity $e, array $options=[]): void
    {
        if (!empty($options['silent']) || !empty($options['skipHooks'])) { return; }
        $old=$e->getFetched('shipmentId'); $new=$e->get('shipmentId');
        if ($old && $old!==$new) { $this->stock->move($e,'undelivered','unlinked',$old); }
        if ($new) {
            $s=$this->em->getEntity('CShipment',$new);
            if ($s && $s->get('paid')) { $this->stock->move($e,'delivered','delivered',$new); }
        }
    }
}
