<?php

use MongoDB\BSON;

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/message.php';
require_once __DIR__ . '/op_codes.php';
require_once __DIR__ . '/scram.php';
require_once __DIR__ . '/socket.php';


class Connection {
  private $options;
  private $handle;
  private $auth;
  private $conn;

  private $isConnected = false;

  /**
   * Creates a new protocol instance
   *
   * @see    https://docs.mongodb.com/manual/reference/connection-string/
   * @param  string $url Mongo url style connection string
   * @param  [:string] $options
   */
  public function __construct(string $url, array $options = []) 
  {
    $this->options = [
      'params' => [],
      ...parse_url($url),
      ...$options,
    ];

    if(isset($this->options['query'])) {
      parse_str($this->options['query'], $params);

      unset($this->options['query']);

      $this->options['params'] += $params;
    }

    $this->conn = new SocketClient(
      $this->options['host'] ?? 'localhost',
      $this->options['port'] ?? 27017
    );

    $this->auth = Auth::mechanism();
  }

  public function endpoint(bool $usePassword = false): string {
    $endpoint = 'tcp://';

    if(isset($this->options['user'])) {
      $pw = ($usePassword ? ($this->options['pass'] ?? 'mongo') : '**********');
      $endpoint .= $this->options['user'] . ':' . $pw . '@';
    }

    $endpoint .= $this->options['host'] . ':' . $this->options['port'] ?? 27017;

    $urlQuery = isset($this->options['path']) ? '&authSource=' . \ltrim($this->options['path'], '/') : '';

    foreach($this->options['params'] as $key => $value) {
      $urlQuery .= '&' . $key . '=' . $value;
    }

    $urlQuery && $endpoint .= '?' . substr($query, 1);

    return 'tcp://127.0.0.1:27017';  // $endpoint;
  }

  public function connect() {
    if($this->isConnected) return;

    $this->conn->connect($this->endpoint(true));

    $response = $this->send(OpCode::QUERY, pack(
      'Va*xVVa*',
      0,
      'admin.$cmd',
      0,
      1,
      $this->messageToBSON([
        'isMaster' => 1,
        'client' => [
          'application' => [
            'name' => $this->options['params']['appName'] ?? $_SERVER['argv'][0]
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
      ])
    ));

    var_dump($response);

    if($response == null) {
      die("Response is empty");
    }

    $this->options['connection_details'] = \current($response['documents']);

    try {
      $source = $this->options['params']['authSource'] ?? (isset($this->options['path']) ? ltrim($this->options['path'], '/') : 'admin');

      $dialog = $this->dialog(
        \urldecode($this->options['user']),
        \urldecode($this->options['pass']),
        $source,
      );

      // while($source->valid()) {
      //   $result = $this->send(
      //     OpCode::MSG,
      //     pack(
      //       'Vca*',
      //       0,
      //       'admin.$cmd',
            
      //       messageToBSON()
      //     )
      //   );
      // }

    } catch(\Exception $e) {
      throw new \Exception('Unable to authenticate with the server');
    }
  }

  public function send($action, $message) {
    $data = gettype($message) === 'string' ? $message : $this->messageToBSON($message);

    $body = pack('VVVV', strlen($data) + 16, 1, 0, $action) . $data;

    $res = $this->conn->write($body);

    echo "\$res:";
    var_dump($res);

    // $header = \unpack('VmessageLength/VrequestID/VresponseTo/VopCode', $this->read(16));
    // $response = $this->read($header['messageLength'] - 16);

    // var_dump($header);
    // var_dump($response);
  }

  public function read($bytes) {
    return $this->conn->readBinary($bytes);
  }

  public function messageToBSON($data): string {
    return BSON\fromPHP($data);
  }
}