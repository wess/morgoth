<?php

error_reporting(E_ALL);
ini_set('display_errors', 'on');

use \MongoDB\BSON as BSON;

// require_once __DIR__ . '/src/auth.php';
// require_once __DIR__ . '/src/connection.php';
// require_once __DIR__ . '/src/message.php';
require_once __DIR__ . '/src/connection.php';
require_once __DIR__ . '/src/adapter.php';
require_once __DIR__ . '/src/scram.php';
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
// define('DB_HOST', '4.tcp.ngrok.io');
// define('DB_PORT', 18636);
define('DB_USER', "mongo");
define('DB_PASS', "mongo");
define('DB_NAME', 'dev');
define('COLLECTION', 'testing3');
define('OBJECT_ID', '5e774940b5309447f90ac037');
define('OP_MSG', 2013);


Swoole\Runtime::enableCoroutine(true);

// $systemUser = "wess";
// $systemPassword = "ssew";

// $manager = new \MongoDB\Driver\Manager(sprintf('mongodb://%s:%s@%s:%d', DB_USER, DB_PASS, DB_HOST, DB_PORT));
// $command = new \MongoDB\Driver\Command([
//   "createUser" => $systemUser,
//   "pwd"        => $systemPassword,
//   "roles"      => array(
//       array("role" => "readWrite", "db" => DB_NAME)
//   )
// ]);

// $cursor = $manager->executeCommand('admin', $command);

// var_dump($cursor);

$pool = new Swoole\ConnectionPool(function() {

  $client = new MongoClient(DB_NAME, DB_HOST, DB_PORT);

  $client->connect();
  
  $res = $client->query([
    'hello' => 1,
  ]);

  $username = DB_USER;
  $password = DB_PASS;  
  $user = utf8_encode($username);

  str_replace("=", "=3D", $user);
  str_replace(",", "=2C", $user);

  $nonce = Hash::generateSalt();

  $first = "n=" . $user . ",r=" . $nonce;

  $payload = new \MongoDB\BSON\Binary("n,," . $first, 0);
  
  $res = $client->query([
    "saslStart" => 1,
    "mechanism" => "SCRAM-SHA-1",
    "payload" => $payload,
    "autoAuthorize" => 1,
    "options" => ["skipEmptyExchange" => true],
  ], 'admin');

  var_dump($res);

  $cid = $res->conversationId;
  $token = $res->payload->getData();
  
  var_dump($res->payload);

  $parts = explode(',', $token);

  if(count($parts) < 3) {
    die("Invalid parts");
  }

  $rnounce = str_replace('r=', '', $parts[0]);
  $salt = str_replace('s=', '', $parts[1]);
  $iteration = str_replace('i=', '', $parts[2]);
  $withoutProof = "c=" . base64_encode("biws") . ",r=" . $rnounce;

  $credentials = $username . ':mongo:' . $password;
  $credentials = utf8_encode(md5(utf8_encode($credentials)));
  $credentials = Hash::hi($credentials, $salt, $iteration);

  $clientKey = Hash::hmacSha1($credentials, "Client Key");
  $storedKey = hash('sha1', $clientKey, true);
  $authMsg = implode(",", [$first, $token, $withoutProof]);
  $clientSig = Hash::hmacSha1($authMsg, $storedKey);
  $clientProof = "p=" . base64_encode($clientKey ^ $clientSig);
  $clientFinal = $withoutProof . ',' . $clientProof;

  echo "\n\n{$clientFinal}\n\n";

  $payload = new \MongoDB\BSON\Binary($clientFinal, 0);

  $res = $client->query([
    "saslContinue" => 1,
    "conversationId" => $cid,
    "payload" => $payload,
  ], 'admin');

  var_dump($res);

  return $client;
}, 10);

Swoole\Coroutine\run(function() use ($pool) {
  echo "RUNNING..\n";

  $result = 'pending';

  $client = $pool->get();

  $adapter = new MongoDBAdapter($client);
  $adapter->setNamespace("testing_namespace");
  // $adapter->list();

  // $adapter->exists(DB_NAME, COLLECTION);

  // $client->insert(COLLECTION, ['message' => 'Torsten!']);
  // $client->insert(COLLECTION, ['message' => 'Wess!']);
  // $client->insert(COLLECTION, ['message' => 'Eldad!']);

  
  // $result = $client->find(COLLECTION, ['_id' => '620a96ee6a351f0edf72a2df']);
  // var_dump($result);
  
  // $client->update(COLLECTION, ['_id' => '620a96ee6a351f0edf72a2df'], ['$set' => ['message' => 'Torsten!']]);

  // $result = $client->find(COLLECTION, ['_id' => '620a96ee6a351f0edf72a2df']);
  
  // $result = $client->delete(COLLECTION, ['_id' => '620a96ee6a351f0edf72a2df']);
  
  // $result = $client->find(COLLECTION, ['_id' => '620a96ee6a351f0edf72a2df']);


  $pool->put($client);
});

