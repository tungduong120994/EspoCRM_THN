<?php
// Isolated calculator/hook tests with in-memory ORM doubles. No database access.
namespace Espo\ORM {
    class Entity {
        public array $fetched;
        public function __construct(public array $data = []) { $this->fetched = $data; }
        public function getId() { return $this->get('id'); }
        public function get($key) { return $this->data[$key] ?? null; }
        public function set($key, $value) { $this->data[$key] = $value; return $this; }
        public function hasFetched($key) { return array_key_exists($key, $this->fetched); }
        public function getFetched($key) { return $this->fetched[$key] ?? null; }
    }
    class EntityManager {
        public array $parcels = [];
        public array $shipments = [];
        public array $orders = [];
        public array $saved = [];
        public function getEntity($type, $id) {
            return ($type === 'CShipment' ? $this->shipments : $this->orders)[$id] ?? null;
        }
        public function getRepository($type) { return new Repository($this); }
        public function saveEntity($entity, $options) {
            if (empty($options['silent'])) { throw new \RuntimeException('Recursive save risk'); }
            $this->saved[] = $entity->getId();
        }
    }
    class Repository {
        private array $where = [];
        public function __construct(private EntityManager $em) {}
        public function where($where) { $this->where = $where; return $this; }
        public function find(): iterable {
            foreach ($this->em->parcels as $p) {
                if (!$p->get('deleted') && $p->get('shipmentId') === $this->where['shipmentId']) {
                    yield $p; // One-shot iterable, like a streaming ORM collection.
                }
            }
        }
    }
}
namespace Espo\Core\Exceptions { class BadRequest extends \RuntimeException {} }
namespace Espo\Core\Di {
    interface EntityManagerAware {}
    trait EntityManagerSetter {
        protected $entityManager;
        public function setEntityManager($em) { $this->entityManager = $em; }
    }
}
namespace {
    use Espo\ORM\Entity;
    use Espo\ORM\EntityManager;
    use Espo\Custom\Services\ShipmentTotalsCalculator;
    use Espo\Custom\Hooks\CParcel\RecalculateParcel;
    use Espo\Custom\Hooks\CShipment\RecalculateTotals;

    $root = __DIR__ . '/../data/espocrm-app/custom/Espo/Custom/';
    require $root . 'Services/ShipmentTotalsCalculator.php';
    require $root . 'Hooks/CParcel/RecalculateParcel.php';
    require $root . 'Hooks/CShipment/RecalculateTotals.php';

    function eq($actual, $expected): void {
        $ok = is_numeric($actual) && is_numeric($expected)
            ? abs((float) $actual - (float) $expected) < 0.00001 : $actual === $expected;
        if (!$ok) { throw new \RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)); }
    }
    function fixture($mode = 'higher'): array {
        $em = new EntityManager();
        $s = new Entity(['id'=>'s1', 'deliveryPricingMode'=>$mode,
            'unitDeliveryPricePerKgVnd'=>10000, 'unitDeliveryPricePerM3Vnd'=>1000000,
            'unitDeliveryPriceVnd'=>20000, 'orderId'=>'o1', 'additionalAmountVnd'=>50000]);
        $em->shipments['s1'] = $s;
        $em->orders['o1'] = new Entity(['remainingAmountVnd'=>200000]);
        // Weight total: 100 kg / 1m VND. Volume total: 2m3 / 2m VND.
        // Per-parcel maxima would wrongly charge 2.8m instead of 2m.
        $em->parcels = [new Entity(['id'=>'p1','shipmentId'=>'s1','weight'=>90,'volume'=>0.1,'packageCount'=>2]),
            new Entity(['id'=>'p2','shipmentId'=>'s1','weight'=>10,'volume'=>1.9,'packageCount'=>1])];
        $calc = new ShipmentTotalsCalculator($em);
        return [$em, $s, $calc];
    }
    $tests = [];
    foreach (['weight'=>1000000, 'volume'=>2000000, 'higher'=>2000000] as $mode=>$expected) {
        $tests['freight ' . $mode] = function () use ($mode, $expected) {
            [$em,$s,$c] = fixture($mode); $c->apply($s);
            eq($s->get('totalDeliveryPriceVnd'), $expected);
            eq($s->get('totalPayableAmountVnd'), $expected + 250000);
            eq($s->get('totalWeight'),100); eq($s->get('totalVolume'),2); eq($s->get('totalPackages'),3);
            eq($em->parcels[0]->get('weight'),90); eq($em->parcels[0]->get('volume'),0.1);
            eq(array_sum(array_map(fn($p)=>$p->get('totalDeliveryPriceVnd'),$em->parcels)), $expected);
        };
    }
    $tests['automatic selects whole-lot maximum, not per-parcel maxima'] = function () {
        [$em,$s,$c] = fixture(); $c->apply($s);
        eq($s->get('deliveryPriceByWeightVnd'),1000000); eq($s->get('deliveryPriceByVolumeVnd'),2000000);
        eq($s->get('appliedDeliveryPricingBasis'),'volume');
        eq($em->parcels[0]->get('totalDeliveryPriceVnd'),100000);
    };
    $tests['warehouse edit switches automatic basis and reprices siblings'] = function () {
        [$em,$s,$c] = fixture(); $c->apply($s);
        $hook = new RecalculateParcel($c); $hook->setEntityManager($em);
        $em->parcels[0]->set('weight',300);
        $hook->beforeSave($em->parcels[0],[]); $hook->afterSave($em->parcels[0],[]);
        eq($s->get('appliedDeliveryPricingBasis'),'weight'); eq($s->get('totalDeliveryPriceVnd'),3100000);
        eq($em->parcels[1]->get('totalDeliveryPriceVnd'),100000);
        eq($em->parcels[0]->get('totalDeliveryPriceVnd'),3000000);
    };
    $tests['tariff edit recalculates parcel amounts'] = function () {
        [$em,$s,$c] = fixture('volume'); $c->apply($s);
        $hook = new RecalculateTotals($c); $hook->setEntityManager($em);
        $s->set('unitDeliveryPricePerM3Vnd',2000000); $hook->beforeSave($s,[]);
        eq($s->get('totalDeliveryPriceVnd'),4000000); eq($em->parcels[1]->get('totalDeliveryPriceVnd'),3800000);
    };
    $tests['deleting a parcel reprices the remaining lot'] = function () {
        [$em,$s,$c] = fixture(); $c->apply($s);
        $hook = new RecalculateParcel($c); $hook->setEntityManager($em);
        $em->parcels[1]->set('deleted',true); $hook->afterRemove($em->parcels[1],[]);
        eq($s->get('totalDeliveryPriceVnd'),900000); eq($s->get('totalPackages'),2);
        eq($em->parcels[0]->get('totalDeliveryPriceVnd'),900000);
    };
    $tests['moving and unlinking updates both shipments'] = function () {
        [$em,$s,$c] = fixture(); $c->apply($s);
        $other = new Entity(['id'=>'s2','deliveryPricingMode'=>'volume','unitDeliveryPricePerM3Vnd'=>500000]);
        $em->shipments['s2']=$other;
        $hook = new RecalculateParcel($c); $hook->setEntityManager($em);
        $p=$em->parcels[1]; $p->set('shipmentId','s2'); $hook->beforeSave($p,[]); $hook->afterSave($p,[]);
        eq($s->get('totalDeliveryPriceVnd'),900000); eq($other->get('totalDeliveryPriceVnd'),950000);
        $p->fetched=$p->data; $p->set('shipmentId',null); $hook->beforeSave($p,[]); $hook->afterSave($p,[]);
        eq($other->get('totalDeliveryPriceVnd'),0); eq($p->get('totalDeliveryPriceVnd'),0);
    };
    $tests['missing measurement never falls back in a selected mode'] = function () {
        [$em,$s,$c] = fixture('volume');
        foreach ($em->parcels as $p) { $p->set('volume',null); }
        $c->apply($s); eq($s->get('totalDeliveryPriceVnd'),0); eq($s->get('totalWeight'),100);
    };
    $tests['legacy formula and stored single tariff are preserved'] = function () {
        [$em,$s,$c]=fixture(null); $c->apply($s);
        eq($s->get('totalDeliveryPriceVnd'),2040000); eq($em->parcels[0]->get('totalDeliveryPriceVnd'),1800000);
        eq($s->get('appliedDeliveryPricingBasis'),null);
        $em->parcels[0]->set('weight',null); $c->apply($s);
        eq($em->parcels[0]->get('totalDeliveryPriceVnd'),2000);
    };
    $tests['missing or negative required rates reject before repricing'] = function () {
        foreach ([null,-1,'',INF] as $bad) {
            [$em,$s,$c]=fixture(); $s->set('unitDeliveryPricePerM3Vnd',$bad);
            try { $c->apply($s); throw new \LogicException('Missing validation'); }
            catch (\Espo\Core\Exceptions\BadRequest $e) { eq(count($em->saved),0); }
        }
        [$em,$s,$c]=fixture('invalid');
        try { $c->apply($s); throw new \LogicException('Missing mode validation'); }
        catch (\Espo\Core\Exceptions\BadRequest $e) { eq(count($em->saved),0); }
    };
    $tests['zero rates, ties and empty lots are deterministic'] = function () {
        [$em,$s,$c]=fixture(); $s->set('unitDeliveryPricePerM3Vnd',500000); $c->apply($s);
        eq($s->get('appliedDeliveryPricingBasis'),'weight');
        $s->set('unitDeliveryPricePerKgVnd',0); $s->set('unitDeliveryPricePerM3Vnd',0); $c->apply($s);
        eq($s->get('totalDeliveryPriceVnd'),0);
        $em->parcels=[]; $c->apply($s); eq($s->get('totalWeight'),0); eq($s->get('totalVolume'),0);
    };
    $tests['manual mode requires only its own rate'] = function () {
        [$em,$s,$c]=fixture('volume'); $s->set('unitDeliveryPricePerKgVnd',null); $c->apply($s);
        eq($s->get('totalDeliveryPriceVnd'),2000000); eq($s->get('deliveryPriceByWeightVnd'),null);
    };
    $tests['skipHooks does not recalculate'] = function () {
        [$em,$s,$c]=fixture(); $s->set('totalDeliveryPriceVnd',123);
        (new RecalculateTotals($c))->beforeSave($s,['skipHooks'=>true]);
        eq($s->get('totalDeliveryPriceVnd'),123);
    };
    foreach ($tests as $name=>$run) { $run(); echo 'PASS: ' . $name . PHP_EOL; }
    echo count($tests) . ' shipment pricing tests passed.' . PHP_EOL;
}
