"""Explicitly authorized UAT refresh. Run on the hosting server with --apply.

Only crm_uat on crm-uat-db-1 is replaced. Production is read only. Secrets and
snapshots remain in the private server directory, never in stdout or Git.
"""
import base64
import datetime
import hashlib
import json
import os
from pathlib import Path
import subprocess
import sys
import time

ROOT = Path('/home/thnglobal/crm-uat')
APP = 'crm-uat-app-1'
DB = 'crm-uat-db-1'
IMAGE = 'espocrm/espocrm:9.2.5'


def run(args, **kwargs):
    result = subprocess.run(args, stdout=subprocess.PIPE, stderr=subprocess.PIPE, **kwargs)
    if result.returncode:
        # Commands may contain credentials. Do not include argv in exceptions.
        raise RuntimeError('Command failed: ' + result.stderr.decode(errors='replace')[:1500])
    return result.stdout


def php(container, code):
    return run(['docker', 'exec', '-i', container, 'php'], input=code.encode())


def offline_php(code, network='none'):
    return run(['docker', 'run', '--rm', '-i', '--network', network,
                '--volumes-from', APP, '--memory', '512m', '--cpus', '0.5',
                '--entrypoint', 'php', IMAGE], input=code.encode())


def inspect(name):
    return json.loads(run(['docker', 'inspect', name]))[0]


def dump(container, user, password, database, destination):
    with destination.open('wb') as handle:
        result = subprocess.run(['docker', 'exec', '-e', 'MYSQL_PWD=' + password,
            container, 'mariadb-dump', '-u' + user, '--single-transaction',
            '--skip-lock-tables', '--hex-blob', '--skip-comments', database],
            stdout=handle, stderr=subprocess.PIPE)
    if result.returncode or destination.stat().st_size < 1000:
        raise RuntimeError('Database dump failed; refresh aborted.')


if sys.argv[1:] != ['--apply'] or Path.cwd() != ROOT:
    raise SystemExit('Run only in /home/thnglobal/crm-uat with --apply after authorization.')
os.umask(0o077)
env = dict(line.split('=', 1) for line in Path('.env').read_text().splitlines() if '=' in line)
assert env['UAT_DOMAIN'] == 'crm-uat.thnglobal.vn'
for name in [APP, DB]:
    assert inspect(name)['Config']['Labels']['com.docker.compose.project'] == 'crm-uat'
assert set(inspect(DB)['NetworkSettings']['Networks']) == {'crm-uat_database'}
assert inspect(DB)['HostConfig']['Memory'] >= 768 * 1024 * 1024, 'UAT DB requires at least 768 MiB for snapshot refresh'
assert next(m['Source'] for m in inspect(APP)['Mounts'] if m['Destination'] == '/var/www/html') == str(ROOT / 'app')
for name in ['crm-uat_database', 'crm-uat_edge']:
    assert json.loads(run(['docker', 'network', 'inspect', name]))[0]['Internal']

stamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')
backup = ROOT / 'backups' / ('refresh-' + stamp)
backup.mkdir(parents=True)
print('Refresh backup:', backup, flush=True)

config_code = "<?php echo json_encode(['config.php'=>include 'data/config.php','config-internal.php'=>include 'data/config-internal.php']);"
source_config = json.loads(php('espocrm', config_code))
old_config = json.loads(php(APP, config_code))
source_db = source_config['config-internal.php'].get('database') or source_config['config.php']['database']
uat_db = old_config['config-internal.php'].get('database') or old_config['config.php']['database']
assert uat_db['dbname'] == 'crm_uat' and uat_db['user'] == 'crm_uat' and uat_db['host'] == 'db'
assert source_db['dbname'] != 'crm_uat'
admin_code = """<?php $c=array_replace_recursive(include 'data/config.php',include 'data/config-internal.php');
$d=$c['database']; assert($d['dbname']==='crm_uat');
$p=new PDO('mysql:host='.$d['host'].';dbname='.$d['dbname'],$d['user'],$d['password']);
echo json_encode($p->query("SELECT * FROM user WHERE user_name='uat-admin' AND deleted=0")->fetch(PDO::FETCH_ASSOC));"""
admin = json.loads(php(APP, admin_code))
assert admin and admin['type'] == 'admin'
(backup / 'uat-admin.json').write_text(json.dumps(admin))
# Snapshot production online with a read-only consistent transaction. No locks or
# changes to source accounts, schemas, application settings or business records.
dump('espocrm-db', source_db['user'], source_db['password'], source_db['dbname'], backup / 'production.sql')
with (backup / 'production-uploads.tar.gz').open('wb') as f:
    result = subprocess.run(['docker', 'exec', 'espocrm', 'tar', 'czf', '-', 'data/upload'], stdout=f, stderr=subprocess.PIPE)
    if result.returncode: raise RuntimeError('Production file snapshot failed')
print('Production SQL and upload snapshots completed.', flush=True)

sql_prefix = ['docker', 'exec', '-i', '-e', 'MYSQL_PWD=' + env['UAT_DB_ROOT_PASSWORD'], DB, 'mariadb', '-uroot']
recreate = b'DROP DATABASE IF EXISTS `crm_uat`; CREATE DATABASE `crm_uat` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'

def restore_sql(path):
    assert path.is_file() and path.stat().st_size > 1000
    run(sql_prefix, input=recreate)
    with path.open('rb') as f:
        result = subprocess.run(sql_prefix + ['crm_uat'], stdin=f, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    if result.returncode: raise RuntimeError('UAT database import failed: ' + result.stderr.decode(errors='replace')[:1000])


run(['docker', 'stop', APP])
backup_complete = False
database_replaced = False
try:
    with (backup / 'uat-app.tar.gz').open('wb') as f:
        result = subprocess.run(['docker','run','--rm','--network','none',
            '--volumes-from',APP,'--memory','256m','--cpus','0.5','--entrypoint','tar',IMAGE,
            'czf','-','custom','client/custom','data/config.php','data/config-internal.php','data/upload'],
            stdout=f,stderr=subprocess.PIPE)
        if result.returncode: raise RuntimeError('UAT file backup failed')
    dump(DB, 'root', env['UAT_DB_ROOT_PASSWORD'], 'crm_uat', backup / 'uat.sql')
    backup_complete = True
    print('UAT stopped and backed up. Replacing UAT database only.', flush=True)
    database_replaced = True
    restore_sql(backup / 'production.sql')
    sanitation = Path('sanitize-database.sql').read_text()
    # Preserve the source active/inactive state and role/team membership, but give
    # users new UAT-only passwords. No copied session/API/integration secrets.
    sanitation = sanitation.replace('UPDATE user SET is_active = 0, password = NULL, api_key = NULL;',
                                    'UPDATE user SET password = NULL, api_key = NULL;')
    assert 'UPDATE user SET is_active = 0' not in sanitation
    run(sql_prefix + ['crm_uat'], input=sanitation.encode())

    # Copy business settings from production, overriding instance and outbound
    # settings with the already-isolated UAT configuration. No source DB secrets
    # are written into the UAT webroot.
    overrides = ['database','siteUrl','applicationName','cronDisabled','useWebSocket',
        'maintenanceMode','authenticationMethod','smtpServer','smtpUsername','smtpPassword',
        'outboundEmailIsShared','assignmentEmailNotifications','portalStreamEmailNotifications',
        'streamEmailNotificationsEntityList','apiSecretKeys','instanceId','hashSecretKey','cryptKey']
    merged_old = dict(old_config['config.php'], **old_config['config-internal.php'])
    for filename, config in source_config.items():
        for key in overrides:
            if key in merged_old: config[key] = merged_old[key]
        config['database'] = uat_db
        config['siteUrl'] = 'https://crm-uat.thnglobal.vn'
        config['applicationName'] = 'THN CRM - UAT'
        config['cronDisabled'] = True
        config['maintenanceMode'] = False
        for key in ['webSocketUrl','webSocketZeroMQSubmissionDsn','webSocketZeroMQSubscriberDsn']:
            config.pop(key, None)
    payload = base64.b64encode(json.dumps(source_config).encode()).decode()
    offline_php("<?php $configs=json_decode(base64_decode('" + payload + "'),true); foreach($configs as $name=>$c){if(!in_array($name,['config.php','config-internal.php'],true)||$c['database']['dbname']!=='crm_uat')throw new RuntimeException('Unsafe config');file_put_contents('data/'.$name,\"<?php\\nreturn \".var_export($c,true).\";\\n\");}")

    # Replace uploaded files only inside the isolated UAT mount. The old directory
    # is retained for rollback, in addition to the compressed backup.
    upload_backup = 'data/upload-before-refresh-' + stamp
    offline_php("<?php if(!is_dir('data/upload')||file_exists('" + upload_backup + "'))throw new RuntimeException('Upload path check failed');rename('data/upload','" + upload_backup + "');")
    run(['docker','run','--rm','--network','none','--volumes-from',APP,
         '--mount','type=bind,src='+str(backup)+',dst=/refresh,readonly','--entrypoint','tar',IMAGE,
         'xzf','/refresh/production-uploads.tar.gz','-C','/var/www/html'])
    user_code = """<?php $c=array_replace_recursive(include 'data/config.php',include 'data/config-internal.php');
$d=$c['database'];if($d['host']!=='db'||$d['dbname']!=='crm_uat')throw new RuntimeException('Unsafe database');
$p=new PDO('mysql:host='.$d['host'].';dbname=crm_uat',$d['user'],$d['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$p->beginTransaction();
$users=$p->query("SELECT id,user_name,type,is_active FROM user WHERE deleted=0")->fetchAll(PDO::FETCH_ASSOC);
$out=[];foreach($users as $u){if($u['is_active'] && in_array($u['type'],['regular','admin','portal'],true)){
$password=bin2hex(random_bytes(16));$q=$p->prepare('UPDATE user SET password=? WHERE id=?');$q->execute([password_hash($password,PASSWORD_BCRYPT),$u['id']]);
$out[]=['id'=>$u['id'],'username'=>$u['user_name'],'password'=>$password,'type'=>$u['type']];}}
$admin=json_decode(base64_decode('ADMIN_PAYLOAD'),true);
$q=$p->prepare('SELECT COUNT(*) FROM user WHERE id=? OR user_name=?');$q->execute([$admin['id'],$admin['user_name']]);
if($q->fetchColumn())throw new RuntimeException('UAT admin identity collision');
$cols=array_keys($admin);$q=$p->prepare('INSERT INTO user (`'.implode('`,`',$cols).'`) VALUES ('.implode(',',array_fill(0,count($cols),'?')).')');$q->execute(array_values($admin));
$p->commit();echo json_encode($out);""".replace('ADMIN_PAYLOAD', base64.b64encode(json.dumps(admin).encode()).decode())
    credentials = offline_php(user_code, 'crm-uat_database')
    json.loads(credentials)
    (ROOT / 'UAT-PROD-BASE-USERS.json').write_bytes(credentials)
    run(['docker','run','--rm','--network','none','--volumes-from',APP,'--entrypoint','chown',IMAGE,
         '-R','www-data:www-data','/var/www/html/data/config.php','/var/www/html/data/config-internal.php','/var/www/html/data/upload'])
    offline_php("<?php require 'bootstrap.php'; (new Espo\\Core\\Application())->run(Espo\\Core\\ApplicationRunners\\Rebuild::class);", 'crm-uat_database')
    run(['docker','start',APP])
    print('Refreshed UAT started. Source user states/roles retained; UAT passwords saved privately.', flush=True)
    (ROOT / 'LAST-REFRESH.json').write_text(json.dumps({'timestamp':stamp,'backup':str(backup),
        'source':'production','productionSqlSha256':hashlib.sha256((backup/'production.sql').read_bytes()).hexdigest()}))
except Exception:
    # A memory-limited DB may have restarted. Wait for recovery before attempting
    # rollback; keep the app stopped if rollback cannot complete safely.
    for attempt in range(30):
        try:
            run(sql_prefix, input=b'SELECT 1;')
            break
        except RuntimeError:
            if attempt == 29: raise
            time.sleep(1)
    if backup_complete and database_replaced:
        restore_sql(backup/'uat.sql')
        run(['docker','run','--rm','--network','none','--volumes-from',APP,
             '--mount','type=bind,src='+str(backup)+',dst=/refresh,readonly','--entrypoint','tar',IMAGE,
             'xzf','/refresh/uat-app.tar.gz','-C','/var/www/html'])
    run(['docker','start',APP])
    raise
