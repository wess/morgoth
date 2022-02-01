<?php

error_reporting(E_ALL);
ini_set('display_errors', 'on');

// require_once __DIR__ . '/src/auth.php';
// require_once __DIR__ . '/src/connection.php';
// require_once __DIR__ . '/src/message.php';
// require_once __DIR__ . '/src/op_codes.php';
// require_once __DIR__ . '/src/scram.php';
// require_once __DIR__ . '/src/socket.php';

// $conn = new Connection('mongodb://mongo@mongo:localhost:27017');

// $conn->connect();

$loadedExtensions = get_loaded_extensions();
$requiredExtensions = ['json', 'mongodb', 'swoole'];
$missingExtensions = array_diff($requiredExtensions, $loadedExtensions);
if (count($missingExtensions) > 0) {
    error_log('The following extension(s) is/are missing: ' . implode(', ', $missingExtensions), 4);
    exit(-1);
}

define('DB_HOST', '127.0.0.1');
define('DB_PORT', 27017);
define('DB_USER', "mongo");
define('DB_PASS', "mongo");
define('DB_NAME', 'dev');
define('COLLECTION', 'test');
define('OBJECT_ID', '5e774940b5309447f90ac037');
define('OP_MSG', 2013);

Swoole\Runtime::enableCoroutine(true);

$manager = new \MongoDB\Driver\Manager(sprintf('mongodb://%s:%s@%s:%d', DB_USER, DB_PASS, DB_HOST, DB_PORT));
$bulk = new \MongoDB\Driver\BulkWrite();
$objectId = new \MongoDB\BSON\ObjectId(OBJECT_ID);
$bulk->update(
    ['_id' => $objectId],
    ['_id' => $objectId, 'message' => 'Hello, World!'],
    ['multi' => false, 'upsert' => true]
);
$manager->executeBulkWrite(sprintf('%s.%s', DB_NAME, COLLECTION), $bulk);

$pool = new Swoole\ConnectionPool(function() {
  $client = new Swoole\Coroutine\Client(SWOOLE_SOCK_TCP);

  $client->connect(DB_HOST, DB_PORT);

  return $client;
}, 10);

Swoole\Coroutine\run(function() use ($pool) {
  $cid = Co::getCid();

  $description = 'This is a test route that uses mongodb wire protocol to run a slow query against database so it responds after 10 seconds but it does block other calls';
  $client = $pool->get();
  $filter = new \stdClass();
  $filter->{'$where'} = 'sleep(10000) || true';
  
  // $sections = \MongoDB\BSON\fromPHP(['find' => COLLECTION, 'filter' => $filter, '$db' => DB_NAME]);
  $sections = \MongoDB\BSON\fromPHP([
    'isMaster' => 1,
    'client' => [
      'application' => [
        'name' => $_SERVER['argv'][0]
      ],
      'driver' => [
        'name' => 'Appwrite Mongo Driver', 
        'version' => '0.0.1'
      ],
      'os' => [
        'name' => php_uname('s'), 
        'type' => PHP_OS, 
        'architecture' => php_uname('m'), 
        'version' => php_uname('r')
      ]
    ]
  ]);

  $message = pack('V*', 21 + strlen($sections), $cid, 0, OP_MSG, 0) . "\0" . $sections;
  $client->send($message);
  $receivedLength = 0;
  $responseLength = null;
  $res = '';
  do {
      if (($chunk = $client->recv()) === false) {
          Co::sleep(0.5); // sleep for half a second to prevent excessive load on CPU
          continue;
      }
      $receivedLength += strlen($chunk);
      $res .= $chunk;
      if ((!isset($responseLength)) && (strlen($res) >= 4)) {
          $responseLength = unpack('Vl', substr($res, 0, 4))['l'];
      }
  } while ((!isset($responseLength)) || ($receivedLength < $responseLength));
  // Returning connection to pool to be reused by others;
  $pool->put($client);
  $result = \MongoDB\BSON\toPHP(substr($res, 21, $responseLength - 21));

  var_dump($result);
});