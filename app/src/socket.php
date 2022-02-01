<?php

set_time_limit(0);


class SocketException extends \Exception {}

class SocketClient
{
  private $host;
  private $port;
  private $conn;

  public function __construct($host, $port) 
  {
    $this->host = $host;
    $this->port = $port;
    $this->conn = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
  }

  public function connect()
  {
    if(!socket_connect($this->conn, '127.0.0.1', 27017)) {
      throw new SocketException('Could not connect to ' . $this->host . ':' . $this->port);
    }
  }

  public function write($data)
  {
    echo 'writing: ' . $data . '\n';
    if(!socket_write($this->conn, $data, strlen($data))) {
      throw new SocketException('Could not write to socket');
    }

    $this->read();
  }

  public function read() {
    ob_implicit_flush();
    
    while ($out = socket_read($this->conn, 1024)) {
      echo $out;
    }
  }
}

// class SocketClient {
//   protected string $host;
//   protected int $port;
//   protected int $timeout;

//   protected bool $connected = false;

//   protected $stream;
//   protected $manager;
//   protected $context = null;
//   protected $eof = false;

//   /**
//    * Socket constructor.
//    * @param string $ip
//    * @param int $port
//    * @param int|float $timeout
//    */
//   public function __construct(string $host = '127.0.0.1', int $port = 27017, int $timeout = 2000) 
//   {
//     $this->host = $host;
//     $this->port = $port;
//     $this->timeout = $timeout;
//     $this->context = stream_context_create();
//   }

//   public function write(string $data):?string
//   {

//     fputs(
//       $this->getStream(),
//       $data,
//       $len = strlen($data)
//     );

//     return $this->read();
//   }


//   protected function getStream()
//   {
//     // First run?
//     if(!is_resource($this->stream))
//     {
//       $this->connect();

//       return $this->stream;
//     }

//     $stream_meta = \stream_get_meta_data($this->stream);
    
//     if($stream_meta['timed_out'] || $stream_meta['eof'])
//     {
//       $this->connect();
//     }
//     return $this->stream;
//   }

//   public function connect(string $uri) 
//   {
//     $stream = \stream_socket_client(
//       $uri,
//       $errno,
//       $errstr,
//       $this->timeout,
//       STREAM_CLIENT_CONNECT,
//       $this->context,
//     );

//     stream_set_blocking($stream, false);
//     stream_context_set_option($this->context, 'socket', 'tcp_nodelay', true);

//     if(!\is_resource($stream)) {
//       throw new SocketException(sprintf('Unable to connect to %s:%d - %s', $this->host, $this->port, $errstr));
//     }

//     $offset = (int)$this->timeout;
//     stream_set_timeout($stream, $offset, (int)(1000 * ($this->timeout - $offset)));
    
//     $this->stream = $stream;
//     $this->connected = true;

//     return $this;
//   }

//   public function disconnect()
//   {
//     if(is_resource($this->stream)) 
//     {
//       \fclose($this->stream);
//     }

//     return $this;
//   }

//   protected function _read($maxLen, $type, $chop= false) {
//     if (null === $this->getStream()) {
//       throw new SocketException('Read of '.$maxLen.' bytes failed: Socket closed');
//     }

//     $res = fgets($this->getStream(), $maxLen);
//     if (false === $res || null === $res) {

//       if (feof($this->getStream())) {
//         $this->eof = true;

//         return null;
//       }
      
//       $m = stream_get_meta_data($this->getStream());

//       if ($m['timed_out']) {
//         die('Read of '.$maxLen.' bytes failed : ' . $this->timeout);
//       } else {
//         die('Read of '.$maxLen.' bytes failed');
//       }
//     } else {
//       return $chop ? chop($res) : $res;
//     }
//   }

//   public function read($maxLen = 4096) {
//     return $this->_read($maxLen, -1, false);
//   }

//   public function readBinary($maxLen= 4096) {
//     if (null === $this->getStream()) {
//       throw new SocketException('Read of '.$maxLen.' bytes failed: Socket closed');
//     }

//     $res= fread($this->getStream(), $maxLen);
//     if (false === $res || null === $res) {
//       $m= stream_get_meta_data($this->getStream());
//       if ($m['timed_out']) {
//         die('Read of '.$maxLen.' bytes failed: ' . $this->timeout);
//       } else {
//         die('Read of '.$maxLen.' bytes failed');
//       }

//     } else if ('' === $res) {
//       $m= stream_get_meta_data($this->getStream());
//       if ($m['timed_out']) {
//         die('Read of '.$maxLen.' bytes failed: '. $this->_timeout);
//       }
//       $this->_eof= true;
//     }
    
//     return $res;
//   }
// }

