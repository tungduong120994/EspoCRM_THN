<?php
// Execute inventory SQL against an isolated SQLite database with ACL doubles.
namespace Espo\Core\Exceptions {
    class BadRequest extends \RuntimeException {}
    class Forbidden extends \RuntimeException {}
    class NotFound extends \RuntimeException {}
}
namespace Espo\ORM {
    class Entity {
        public function __construct(private array $values) {}
        public function get(string $key): mixed { return $this->values[$key] ?? null; }
        public function getId(): string { return $this->get('id'); }
    }
    class EntityManager {
        public function __construct(private \PDO $pdo) {}
        public function getPDO(): \PDO { return $this->pdo; }
        public function getEntity(string $type, string $id): ?Entity {
            $q=$this->pdo->prepare('SELECT id,assigned_user_id AS assignedUserId,deleted FROM account WHERE id=?');
            $q->execute([$id]); $row=$q->fetch(\PDO::FETCH_ASSOC);
            return $row ? new Entity($row) : null;
        }
    }
}
namespace Espo\Entities {
    class User {
        public function isAdmin(): bool { return false; }
        public function getId(): string { return 'sales'; }
    }
}
namespace Espo\Core {
    class Acl {
        public array $levels=['read'=>'own','export'=>'own','edit'=>'no'];
        public string $exportPermission = 'yes';
        public function checkScope($scope,$action): bool {
            if ($action === 'export') { throw new \LogicException('Export is not a built-in scope checker action'); }
            return ($this->levels[$action]??'no')!=='no';
        }
        public function getPermissionLevel($permission): string { return $this->exportPermission; }
        public function getLevel($scope,$action): string { return $this->levels[$action]??'no'; }
        public function check($entity,$action): bool { return false; }
    }
}
namespace {
    use Espo\Custom\Services\Logistics\{Access,Inventory};
    use Espo\Core\Exceptions\{Forbidden,BadRequest};
    $root=__DIR__.'/../data/espocrm-app/custom/Espo/Custom/Services/Logistics/';
    require $root.'Access.php'; require $root.'Inventory.php';
    $pdo=new \PDO('sqlite::memory:'); $pdo->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE account(id TEXT,name TEXT,c_name TEXT,assigned_user_id TEXT,deleted INTEGER)');
    $pdo->exec('CREATE TABLE c_parcel(id TEXT,account_id TEXT,name TEXT,package_count INTEGER,weight REAL,volume REAL,status TEXT,created_at TEXT,delivered_at TEXT,returned_at TEXT,deleted INTEGER)');
    $pdo->exec("INSERT INTO account VALUES ('a','A','Assigned','sales',0),('b','B','Other','other',0),('c','C','Empty','sales',0),('d','D','Deleted','sales',1)");
    $insert=$pdo->prepare('INSERT INTO c_parcel VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    foreach ([
        ['p1','a','001',3,10,1,'undelivered','2026-09-14 16:59:59',null,null,0],
        ['p2','a','002',2,20,2,'delivered','2026-09-14 17:00:00','2026-09-15 01:00:00',null,0],
        ['p3','a','003',4,30,3,'returned','2026-09-15 16:59:59',null,'2026-09-15 02:00:00',0],
        ['p4','a','004',1,40,4,'undelivered','2026-09-15 17:00:00',null,null,0],
        ['p5','a','deleted',1,999,99,'undelivered','2026-09-15 01:00:00',null,null,1],
        ['p6','b','other',1,999,99,'undelivered','2026-09-15 01:00:00',null,null,0],
    ] as $row) { $insert->execute($row); }
    $acl=new \Espo\Core\Acl(); $em=new \Espo\ORM\EntityManager($pdo);
    $access=new Access($acl,new \Espo\Entities\User(),$em); $inventory=new Inventory($em,$access);
    $count=0;
    function same($a,$b,$label): void {
        global $count; if ($a!==$b) { throw new \RuntimeException($label.': '.json_encode([$a,$b])); }
        ++$count; echo "PASS: $label\n";
    }
    function denied(callable $fn,string $class,string $label): void {
        try { $fn(); } catch (\Throwable $e) { if ($e instanceof $class) { same(true,true,$label); return; } throw $e; }
        throw new \RuntimeException('Expected rejection: '.$label);
    }
    $dates=['from'=>'2026-09-15','to'=>'2026-09-15'];
    $result=$inventory->accounts($dates);
    same($result['total'],2,'sales sees only assigned active accounts');
    same($result['list'][1]['stockTracking'],0,'customers with no stock remain visible');
    same($result['list'][0]['receivedTracking'],2,'receipt day uses inclusive Vietnam day boundaries');
    same($result['list'][0]['receivedWeight'],50.0,'received weight follows date filter');
    same($result['list'][0]['stockTracking'],3,'stock counts tracking, includes returns, excludes delivered/deleted');
    same($result['list'][0]['stockWeight'],80.0,'current stock is independent of receipt date filter');
    $detail=$inventory->tracking($dates+['accountId'=>'a','receivedOnly'=>'true']);
    same($detail['total'],2,'received tracking drilldown uses same date boundaries');
    same($detail['list'][0]['receivedAt'],'2026-09-15 23:59:59','tracking timestamps shown in Vietnam time');
    denied(fn()=>$inventory->tracking(['accountId'=>'b']),Forbidden::class,'direct tracking API rejects another sales account');
    denied(fn()=>$inventory->tracking(['accountId'=>'b'],true),Forbidden::class,'direct export rejects another sales account');
    $acl->levels['read']='all';
    same($inventory->accounts($dates)['total'],3,'warehouse read-all includes all active accounts');
    same($inventory->accounts($dates,'CInventory',true)['total'],2,'export-own still restricts read-all user');
    $acl->levels['export']='no';
    denied(fn()=>$inventory->accounts($dates,'CInventory',true),Forbidden::class,'no-export permission enforced on server');
    $acl->levels['read']='own'; $acl->levels['export']='all';
    same($inventory->accounts($dates,'CInventory',true)['total'],2,'export-all does not bypass read-own');
    $acl->exportPermission='no';
    denied(fn()=>$inventory->accounts($dates,'CInventory',true),Forbidden::class,'global export denial overrides custom scope allowance');
    $acl->exportPermission='yes';
    denied(fn()=>$inventory->accounts(['from'=>'2026-02-30']),BadRequest::class,'invalid calendar date rejected');
    denied(fn()=>$inventory->accounts(['from'=>'2026-09-16','to'=>'2026-09-15']),BadRequest::class,'reversed interval rejected');
    same($inventory->accounts(['q'=>"' OR 1=1 --"])['total'],0,'search text is bound as data');
    same($access->canEditFinance('a'),false,'read-only finance account cannot mutate ledger');
    echo "$count inventory SQL/access assertions passed.\n";
}
