<?php

error_reporting(E_ALL);
ini_set('display_errors', 'on');

// require_once __DIR__ . '/src/auth.php';
// require_once __DIR__ . '/src/connection.php';
// require_once __DIR__ . '/src/message.php';
require_once __DIR__ . '/src/op_codes.php';
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
define('DB_USER', "mongo");
define('DB_PASS', "mongo");
define('DB_NAME', 'dev');
define('COLLECTION', 'test');
define('OBJECT_ID', '5e774940b5309447f90ac037');
define('OP_MSG', 2013);

Swoole\Runtime::enableCoroutine(true);



// $manager = new \MongoDB\Driver\Manager(sprintf('mongodb://%s:%s@%s:%d', DB_USER, DB_PASS, DB_HOST, DB_PORT));
// $bulk = new \MongoDB\Driver\BulkWrite();
// $objectId = new \MongoDB\BSON\ObjectId(OBJECT_ID);
// $bulk->update(
//     ['_id' => $objectId],
//     ['_id' => $objectId, 'message' => 'Hello, World!'],
//     ['multi' => false, 'upsert' => true]
// );
// $manager->executeBulkWrite(sprintf('%s.%s', DB_NAME, COLLECTION), $bulk);

$pool = new Swoole\ConnectionPool(function() {
  $client = new Swoole\Coroutine\Client(SWOOLE_SOCK_TCP);

  $client->connect(DB_HOST, DB_PORT);

  return $client;
}, 10);

Swoole\Coroutine\run(function() use ($pool) {
  echo "RUNNING..\n";

  $cid = Co::getCid();

  $description = 'This is a test route that uses mongodb wire protocol to run a slow query against database so it responds after 10 seconds but it does block other calls';
  $client = $pool->get();
  
  // $filter = new \stdClass();
  // $filter->{'$where'} = 'sleep(10000) || true';
  // $sections = \MongoDB\BSON\fromPHP(['find' => COLLECTION, 'filter' => $filter, '$db' => DB_NAME]);

  $sections = \MongoDB\BSON\fromPHP([
    'isMaster' => 1,
    'client'   => [
      'application' => ['name' => 'Appwrite'],
      'driver'      => ['name' => 'Appwrite MongoDB Driver', 'version' => '0.0.1'],
      'os'          => ['name' => php_uname('s'), 'type' => PHP_OS, 'architecture' => php_uname('m'), 'version' => php_uname('r')]
    ]
  ]);

  $message = pack(
    'Va*xVVa*',
    0,
    'admin.$cmd',
    0,
    1,
    $sections
  );

  $body = pack('VVVV*', strlen($message) + 16, $cid, 0, OpCode::QUERY) . $message;
  $client->send($body);

  $response = $client->recv();

  $header = unpack('VmessageLength/VrequestID/VresponseTo/VopCode', $response);

  var_dump($header);

  $reply = unpack('VresponseFlags/QcursorID/VstartingFrom/VnumberReturned', $response);

  var_dump($reply);

  $chunk = substr($response, $header["messageLength"] - count($header));

  //x[:documents], _ = receive_bson(chunk, 20, x[:numberReturned])

  var_dump($chunk);

  $documents = [];
  $start = 0;
  $max = $reply['numberReturned'];

  $res = \MongoDB\BSON\toJSON($chunk);

  var_dump($res);

  // while($start < strlen($chunk) && sizeof($documents) < $max) {
  //   $bLength = unpack('V', substr($chunk, $start, 4))[1];

  //   // $documents[] = \MongoDB\BSON\toPHP();

  //   $offset = substr($chunk, $start , $bLength - 1);

  //   // $binary = new \MongoDB\BSON\Binary($offset, MongoDB\BSON\Binary::TYPE_GENERIC);
    
  //   // var_dump($binary);

  //   $start += $bLength;
  // }

  // var_dump($documents);

  // echo($header['opCode']);

  // $reply = unpack('VresponseFlags/PcursorID/VstartingFrom/VnumberReturned', substr($response, 0, 20));

  // $offset = 20;
  // $reply['documents'] = [];

  // for($i = 0; $i < $reply['numberReturned']; $i++) {
  //   $length = unpack('V', substr($response, $offset, 4))[1];

  //   print_r($length); echo '\n';
  //   $offset++;
  // }

  // var_dump($reply);
  
  // $receivedLength = 0;
  // $responseLength = null;
  // $res = '';
  // do {
  //     if (($chunk = $client->recv()) === false) {
  //         Co::sleep(0.5); // sleep for half a second to prevent excessive load on CPU
  //         continue;
  //     }
  //     $receivedLength += strlen($chunk);
  //     $res .= $chunk;
  //     if ((!isset($responseLength)) && (strlen($res) >= 4)) {
  //         $responseLength = unpack('Vl', substr($res, 0, 4))['l'];
  //     }
  // } while ((!isset($responseLength)) || ($receivedLength < $responseLength));
  // // Returning connection to pool to be reused by others;
  // $pool->put($client);
  // // $result = \MongoDB\BSON\toPHP(substr($res, 21, $responseLength - 21))->cursor->firstBatch;
  // $result = \MongoDB\BSON\toPHP(substr($res, 21, $responseLength - 21));

  // echo "RESULT: \n";
  // var_dump($result);
});