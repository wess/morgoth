<?php

use \MongoDB\BSON as BSON;

class MongoCommand {
  const CREATE = "create";
  const DELETE = "delete";
  const FIND = "find";
  const FIND_AND_MODIFY = "findAndModify";
  const GET_LAST_ERROR = "getLastError";
  const GET_MORE = "getMore";
  const INSERT = "insert";
  const RESET_ERROR = "resetError";
  const UPDATE = "update";
  const COUNT = "count";
  const AGGREGATE = "aggregate";
  const DISTINCT = "distinct";
  const MAP_REDUCE = "mapReduce";
}


class MongoClient {
  private $id;
  private $name;
  private $host;
  private $port;
  private $client;
  
  public function __construct($name, $host, $port) {
    $this->id = uniqid('mongo_client');
    $this->name = $name;
    $this->host = $host;
    $this->port = $port;
    $this->client = new Swoole\Coroutine\Client(SWOOLE_SOCK_TCP);
  }

  public function connect() {
    $this->client->connect($this->host, $this->port);

    return $this;
  }

  private function query($command) {

    $sections = BSON\fromPHP([
      ...$command,
      '$db' => $this->name,
    ]);

    $message = pack('V*', 21 + strlen($sections), $this->id, 0, 2013, 0) . "\0" . $sections;

    return $this->send($message);
  }

  private function send($data) {
    $this->client->send($data);

    return $this->receive();
  }

  private function receive() {
    $receivedLength = 0;
    $responseLength = null;
    $res = '';

    do {
      if (($chunk = $this->client->recv()) === false) {
          Co::sleep(0.5); // Prevent excessive CPU Load, test lower.
          continue;
      }
      
      $receivedLength += strlen($chunk);
      $res .= $chunk;

      if ((!isset($responseLength)) && (strlen($res) >= 4)) {
          $responseLength = unpack('Vl', substr($res, 0, 4))['l'];
      }

    } while (
      (!isset($responseLength)) || ($receivedLength < $responseLength) 
    );

    $result = BSON\toPHP(substr($res, 21, $responseLength - 21));

    var_dump($result);

    if(property_exists($result, "n") && $result->n > 0 && $result->ok == 1) {
      return "ok";
    }

    if(property_exists($result, 'errmsg')) {
      die($result->errmsg);
    }

    return $result->cursor->firstBatch;
  }

  // For options see: https://docs.mongodb.com/manual/reference/command/create/#mongodb-dbcommand-dbcmd.create
  public function createCollection($name, $options = []) {
    $this->query($name, [
      ...$options
    ]);

    return $this;
  }

  // https://docs.mongodb.com/manual/reference/command/drop/#mongodb-dbcommand-dbcmd.drop
  public function dropCollection($name, $options = []) {
    $this->query($name, [
      ...$options
    ]);

    return $this;
  }

  // https://docs.mongodb.com/manual/reference/command/createIndexes/#createindexes
  public function createIndexes($collection, $indexes, $options = []) {
    $this->query([
      'createIndexes' => $collection,
      'indexes' => $indexes,
      ...$options
    ]);

    return $this;
  }

  // https://docs.mongodb.com/manual/reference/command/dropIndexes/#dropindexes
  public function dropIndexes($collection, $indexes, $options = []) {
    $this->query([
      'dropIndexes' => $collection,
      'indexes' => $indexes,
      ...$options
    ]);

    return $this;
  }

  // https://docs.mongodb.com/manual/reference/command/insert/#mongodb-dbcommand-dbcmd.insert
  public function insert($collection, $documents, $options = []) {
    $documents = is_array($documents) ? $documents : [$documents];
    
    $docObjects = [];
    foreach($documents as $doc) {
      foreach((object)$doc as $k=>$value) {
        $docObj = new \stdClass();
        $docObj->{$k} = $value;

        $docObjects[] = $docObj;
      }
    }

    $this->query([
      MongoCommand::INSERT => $collection, 
      'documents' => $docObjects, 
      ...$options
    ]);

    return $this;
  }

  // https://docs.mongodb.com/manual/reference/command/update/#syntax
  public function update($collection, $updates = [], $options = []) {
    $documents = is_array($documents) ? $documents : [$documents];
    
    $docObjects = [];
    foreach($documents as $doc) {
      foreach((object)$doc as $k=>$value) {
        $docObj = new \stdClass();
        $docObj->{$k} = $value;

        $docObjects[] = $docObj;
      }
    }

    $this->query([
      MongoCommand::UPDATE => $collection, 
      'documents' => $docObjects, 
      ...$options
    ]);

    return $this;
  }

  // https://docs.mongodb.com/manual/reference/command/find/#mongodb-dbcommand-dbcmd.find
  public function find($collection, $filters = [], $options = []) {
    return $this->query([
      MongoCommand::FIND => $collection,
      'filter' => $this->toObject($filters),
      ...$options,
    ]);
  }

  // https://docs.mongodb.com/manual/reference/command/findAndModify/#mongodb-dbcommand-dbcmd.findAndModify
  public function findAndModify($collection, $document, $remove = false, $update, $filters = [], $options = []) {
    return $this->query([
      MongoCommand::FIND_AND_MODIFY => $collection,
      'filter' => $this->toObject($filters),
      'remove' => $remove,
      'update' => $update,
      ...$options,
    ]);
  }

  // https://docs.mongodb.com/manual/reference/command/delete/#mongodb-dbcommand-dbcmd.delete
  public function delete($collection, $filters = [], $limit = 1, $deleteOptions = [], $options = []) {
    return $this->query([
      MongoCommand::DELETE => $collection,
      'deletes' => [
        $this->toObject(
          [
            'q' => $this->toObject($filters),
            'limit' => $limit,
            ...$deleteOptions
          ]
        ),
      ],
      ...$options,
    ]);
  }


  private function toObject($dict) {
    $obj = new \stdClass();

    foreach($dict as $k => $v) {
      $obj->{$k} = $v;
    }

    return $obj;
  }
}
