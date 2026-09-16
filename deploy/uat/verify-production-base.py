"""Read-only comparison of production and UAT base records after refresh.

Run only on the hosting server from /home/thnglobal/crm-uat. A difference can
also mean production changed after its online snapshot; inspect before acting.
No customer names, credentials or record contents are printed or written.
"""
import base64
import json
from pathlib import Path
import subprocess

assert Path.cwd() == Path('/home/thnglobal/crm-uat')
tables = ['user', 'team', 'role', 'team_user', 'role_user', 'role_team',
          'account', 'c_order', 'c_order_item', 'c_parcel', 'c_shipment']


def php(container, code):
    result = subprocess.run(['docker','exec','-i',container,'php'],input=code.encode(),
                            stdout=subprocess.PIPE,stderr=subprocess.PIPE)
    if result.returncode:
        raise RuntimeError(result.stderr.decode(errors='replace')[:1000])
    return json.loads(result.stdout)


connect = """<?php $c=array_replace_recursive(include 'data/config.php',include 'data/config-internal.php');
$d=$c['database'];$p=new PDO('mysql:host='.$d['host'].';dbname='.$d['dbname'],$d['user'],$d['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
"""
schema = php('espocrm', connect + '$out=[];foreach(' +
    "json_decode(base64_decode('" + base64.b64encode(json.dumps(tables).encode()).decode() + "'),true)" +
    " as $t){$out[$t]=$p->query('SHOW COLUMNS FROM `'.$t.'`')->fetchAll(PDO::FETCH_COLUMN);}echo json_encode($out);")
# Preserve identities, activation state, ownership and assignments. Login secrets
# deliberately differ, while runtime fields such as last access can change live.
schema['user'] = ['id','user_name','type','is_active','deleted']
payload = base64.b64encode(json.dumps(schema).encode()).decode()
query = connect + """
$schema=json_decode(base64_decode('PAYLOAD'),true);$out=[];
foreach($schema as $table=>$cols){
$sql='SELECT `'.implode('`,`',$cols).'` FROM `'.$table.'`';
if($table==='user')$sql.=" WHERE user_name <> 'uat-admin' OR user_name IS NULL";
$rows=$p->query($sql)->fetchAll(PDO::FETCH_NUM);
$lines=array_map(fn($r)=>json_encode($r,JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION),$rows);
sort($lines,SORT_STRING);$out[$table]=['count'=>count($lines),'sha256'=>hash('sha256',implode("\\n",$lines))];
}echo json_encode($out);
""".replace('PAYLOAD',payload)
source = php('espocrm',query)
uat = php('crm-uat-app-1',query)
report = {name:{'sourceCount':source[name]['count'],'uatCount':uat[name]['count'],
                'matches':source[name]==uat[name]} for name in tables}
Path('BASE-COMPARISON.json').write_text(json.dumps(report,indent=2))
for name,result in report.items():
    print(name, 'MATCH' if result['matches'] else 'DIFF',
          'production='+str(result['sourceCount']), 'uat='+str(result['uatCount']))
if not all(value['matches'] for value in report.values()):
    raise SystemExit('Differences require review; no corrective write was performed.')
