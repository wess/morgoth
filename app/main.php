<?php

error_reporting(E_ALL);
ini_set('display_errors', 'on');

// require_once __DIR__ . '/src/auth.php';
// require_once __DIR__ . '/src/connection.php';
// require_once __DIR__ . '/src/message.php';
require_once __DIR__ . '/src/connection.php';
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

define('DB_HOST', '192.168.7.216');
define('DB_PORT', 27017);
// define('DB_USER', "mongo");
// define('DB_PASS', "mongo");
define('DB_NAME', 'dev');
define('COLLECTION', 'testing3');
define('OBJECT_ID', '5e774940b5309447f90ac037');
define('OP_MSG', 2013);

Swoole\Runtime::enableCoroutine(true);



// $manager = new \MongoDB\Driver\Manager(sprintf('mongodb://%s:%d', DB_HOST, DB_PORT));
// $bulk = new \MongoDB\Driver\BulkWrite();
// $objectId = new \MongoDB\BSON\ObjectId(OBJECT_ID);
// $bulk->update(
//     ['_id' => $objectId],
//     ['_id' => $objectId, 'message' => 'Torsten!'],
//     ['multi' => false, 'upsert' => true]
// );
// $manager->executeBulkWrite(sprintf('%s.%s', DB_NAME, COLLECTION), $bulk);

$pool = new Swoole\ConnectionPool(function() {
  $client = new MongoClient(DB_NAME, DB_HOST, DB_PORT);

  $client->connect();

  return $client;
}, 10);

Swoole\Coroutine\run(function() use ($pool) {
  echo "RUNNING..\n";

  $result = 'pending';

  $client = $pool->get();

  // $client->insert(COLLECTION, ['message' => 'Torsten!']);
  // $client->insert(COLLECTION, ['message' => 'Wess!']);
  // $client->insert(COLLECTION, ['message' => 'Eldad!']);

  
  $result = $client->find(COLLECTION, ['_id' => '620a96ee6a351f0edf72a2df']);
  var_dump($result);
  
  $client->update(COLLECTION, ['_id' => '620a96ee6a351f0edf72a2df'], ['$set' => ['message' => 'Torsten!']]);

  $result = $client->find(COLLECTION, ['_id' => '620a96ee6a351f0edf72a2df']);
  
  $result = $client->delete(COLLECTION, ['_id' => '620a96ee6a351f0edf72a2df']);
  
  $result = $client->find(COLLECTION, ['_id' => '620a96ee6a351f0edf72a2df']);
  
  var_dump($result);

  $pool->put($client);
});

