<?php
// Domain tests only: no application bootstrap, database or production access.
namespace Espo\Core\Exceptions { class BadRequest extends \RuntimeException {} }
namespace Espo\ORM {
    class Entity {
        public function __construct(private array $values = []) {}
        public function get(string $key): mixed { return $this->values[$key] ?? null; }
        public function getId(): ?string { return $this->get('id'); }
        public function set(string $key, mixed $value): void { $this->values[$key] = $value; }
    }
    class EntityManager {
        public function getRepository(string $type): object {
            return new class {
                public function where(array $where): self { return $this; }
                public function find(): array { return [new Entity(['quantity'=>1,'totalPriceCny'=>100])]; }
            };
        }
    }
}
namespace {
    use Espo\Custom\Services\Logistics\{Money, Balance, OrderFees, Snapshot};
    use Espo\Core\Exceptions\BadRequest;
    use Espo\ORM\Entity;
    $root = __DIR__ . '/../data/espocrm-app/custom/Espo/Custom/Services/Logistics/';
    foreach (['Money','Balance','OrderFees','Snapshot'] as $class) { require $root . $class . '.php'; }
    require $root . '../OrderTotalsCalculator.php';
    $count = 0;
    function same($actual, $expected, string $label): void {
        global $count;
        if ($actual !== $expected) { throw new \RuntimeException($label . ': ' . json_encode([$actual,$expected])); }
        ++$count; echo "PASS: $label\n";
    }
    function invalid(callable $fn, string $label): void {
        try { $fn(); } catch (BadRequest $e) { same(true, true, $label); return; }
        throw new \RuntimeException('Expected rejection: ' . $label);
    }
    function entry(string $kind, float $amount, ?string $id = null): array {
        return ['kind'=>$kind,'amountVnd'=>$amount,'shipmentId'=>$id];
    }
    $invoices = [
        ['id'=>'a','workflowStatus'=>'confirmed','postedAmountVnd'=>100],
        ['id'=>'b','workflowStatus'=>'confirmed','postedAmountVnd'=>200],
        ['id'=>'draft','workflowStatus'=>'draft','postedAmountVnd'=>999],
    ];
    $entries = [entry('receipt',350), entry('allocation',100,'a'), entry('allocation',150,'b')];
    $b = Balance::calculate($invoices,$entries);
    same($b['shipments']['a']['due'], 0, 'first invoice fully paid');
    same($b['shipments']['b']['due'], 5000, 'second invoice partially paid');
    same($b['shipments']['draft']['charge'], 0, 'draft does not accrue debt');
    same($b['credit'], 10000, 'one receipt pays several invoices and retains unused credit');
    same($b['balance'], -5000, 'net balance includes unused credit only once');
    $entries[] = entry('returnCredit',20,'a');
    $entries[] = entry('release',20,'a');
    $b = Balance::calculate($invoices,$entries);
    same($b['shipments']['a']['net'], 8000, 'return reduces original invoice charge');
    same($b['shipments']['a']['paid'], 8000, 'return releases only excess allocation');
    same($b['credit'], 12000, 'return does not count both credit note and release as cash');
    $invoices[0]['workflowStatus'] = 'cancelled';
    $entries[] = entry('release',80,'a');
    $b = Balance::calculate($invoices,$entries);
    same($b['credit'], 20000, 'cancellation preserves received money after prior return');
    same($b['shipments']['a']['due'], 0, 'cancelled invoice has no debt');
    $b = Balance::calculate([], [entry('openingDebit',120),entry('openingCredit',50),entry('allocation',30)]);
    same($b['openingDue'], 9000, 'opening debt accepts partial allocation');
    same($b['credit'], 2000, 'opening credit remainder is retained');
    same($b['balance'], 7000, 'opening net balance is correct');
    same(Money::minor('10.005'), 1001, 'half-up money rounding');
    same(Money::percent(1000000,1.5), 15000.0, 'fractional percentage is entered as whole percent');
    foreach ([null,'abc',INF,NAN,1e13] as $bad) { invalid(fn()=>Money::minor($bad), 'invalid amount rejected'); }
    invalid(fn()=>Money::positive(0), 'zero receipt rejected');
    invalid(fn()=>Money::percent(100,101), 'percentage above 100 rejected');
    invalid(fn()=>Money::percent(-1,5), 'negative valuation rejected');
    $order = new Entity(['formalImport'=>true,'declaredValueVnd'=>1000000,'documentFeeVnd'=>300000,
        'entrustmentPercent'=>1.5,'importTaxPercent'=>5,'vatPercent'=>8]);
    same(OrderFees::apply($order), 445000.0, 'declared fees use declared value independently');
    same($order->get('vatVnd'), 80000.0, 'VAT uses approved declared-value basis');
    same(OrderFees::apply(new Entity(['formalImport'=>false])), 0.0, 'legacy order adds no declared fees');
    invalid(fn()=>OrderFees::apply(new Entity(['formalImport'=>true])), 'formal import requires declared value');
    $calculator = new \Espo\Custom\Services\OrderTotalsCalculator(new \Espo\ORM\EntityManager());
    $legacy = new Entity(['id'=>'order','exchangeRate'=>10000,'serviceFeeVndPercent'=>0.02]);
    $calculator->apply($legacy);
    same($legacy->get('serviceFeeVnd'),20000.0,'legacy stored fraction remains 2 percent');
    same($legacy->get('grandTotalPriceVnd'),1020000.0,'legacy service fee is added exactly once');
    $formalOrder = new Entity(['id'=>'formal','exchangeRate'=>10000,'serviceFeeRatePercent'=>1.5,
        'formalImport'=>true,'declaredValueVnd'=>1000000,'documentFeeVnd'=>300000,
        'entrustmentPercent'=>1.5,'importTaxPercent'=>5,'vatPercent'=>8,'depositAmountVnd'=>100000]);
    $calculator->apply($formalOrder);
    same($formalOrder->get('grandTotalPriceVnd'),1460000.0,'service and declared fees are each added once');
    same($formalOrder->get('remainingAmountVnd'),1360000.0,'deposit deducted after all fees');
    $calculator->apply($formalOrder);
    same($formalOrder->get('grandTotalPriceVnd'),1460000.0,'repeat recalculation does not accumulate fees');
    $snapshot = ['number'=>'<script>alert(1)</script>','accountCode'=>'A','accountName'=>'Customer',
        'date'=>'2026-09-15','orders'=>[],'parcels'=>[],'freight'=>10,'additional'=>0,
        'surcharge'=>'<img src=x onerror=alert(1)>','amount'=>100,'oldBalance'=>20,'requested'=>120];
    $html = Snapshot::html($snapshot);
    same(str_contains($html,'<script>'), false, 'snapshot escapes customer-controlled markup');
    same(str_contains($html,'&lt;img'), true, 'snapshot escapes surcharge text');
    same(str_contains($html,'120,00 VND'), true, 'printed requested amount uses supplied snapshot');
    echo "$count logistics domain assertions passed.\n";
}
