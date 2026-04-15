<?php
 return array (
  'database' => 
  array (
    'host' => 'espocrm-db',
    'port' => '3306',
    'charset' => NULL,
    'dbname' => 'espocrm',
    'user' => 'espocrm',
    'password' => 'qXXOtXFbriK2upcWXXHhv749xRTDwhub2LyzthE32Lli5GRYP0',
    'platform' => 'Mysql',
  ),
  'smtpPassword' => NULL,
  'logger' => 
  array (
    'path' => 'data/logs/espo.log',
    'level' => 'WARNING',
    'rotation' => true,
    'maxFileNumber' => 30,
    'printTrace' => false,
    'databaseHandler' => false,
    'sql' => false,
    'sqlFailed' => false,
  ),
  'restrictedMode' => false,
  'cleanupAppLog' => true,
  'cleanupAppLogPeriod' => '30 days',
  'webSocketMessager' => 'ZeroMQ',
  'clientSecurityHeadersDisabled' => false,
  'clientCspDisabled' => false,
  'clientCspScriptSourceList' => 
  array (
    0 => 'https://maps.googleapis.com',
  ),
  'adminUpgradeDisabled' => false,
  'isInstalled' => true,
  'microtimeInternal' => 1766028472.998134,
  'cryptKey' => '2c361ebb47e0bec42cf6401931388d5d',
  'hashSecretKey' => '660a662a8c9d2227bbabadaa376dad0c',
  'defaultPermissions' => 
  array (
    'user' => 'www-data',
    'group' => 'www-data',
  ),
  'actualDatabaseType' => 'mariadb',
  'actualDatabaseVersion' => '11.3.2',
  'instanceId' => 'b922ba9e-0fc0-4e3d-a7c6-3eabfe401c74',
  'webSocketZeroMQSubmissionDsn' => 'tcp://espocrm-websocket:7777',
  'webSocketZeroMQSubscriberDsn' => 'tcp://*:7777',
);
