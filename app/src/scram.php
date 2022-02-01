<?php

// RFC: https://datatracker.ietf.org/doc/html/rfc5802

class Scram {
  const ITERATIONS = 4096;

  // Mongo says SHA1 or SHA256. We will start with 256 and see if we need another option.
  const ALGORITHM = 'sha256'; 

  private $nonce= null;

  public function __construct() 
  {
    $this->nonce= function() { return base64_encode(random_bytes(24)); };
  }

  public function nonce($callable) 
  {
    $this->nonce= $callable;
    return $this;
  }

  private function pairs($payload) 
  {
    $result = [];
    $data = explode(',', $payload);

    foreach ($data as $entity) 
    {
      sscanf(
        $entity, 
        "%[^=]=%[^\r]", 
        $key, 
        $value
      );

      $result[$key]= $value;
    }
    
    return $result;
  }

  private function convertBytes($bytes) 
  {
    // https://www.php.net/manual/en/function.addcslashes.php
    return '(' . strlen($bytes) . ')' . '@' . '{' . \addcslashes($bytes, "\0..\37\177..\377") . '}';
  }

  public function dialog(string $username, string $password, string $source) 
  {
    $gs2 = 'n,,';
    $nonce = ($this->nonce)();
    $c1b = 'n='.$username.',r='.$nonce;

    $first= yield [
      'saslStart' => 1,
      'mechanism' => 'SCRAM-SHA-1',
      'payload'   => $this->convertBytes($gs2 . $c1b),
      '$db'       => $source,
    ];

    $pairs= $this->pairs($first['payload']);

    if (0 !== substr_compare($pairs['r'], $nonce, 0, strlen($nonce))) {
      throw new Exception('Server did not extend client nonce '.$nonce.' ('.$pairs['r'].')');
    }

    if ($pairs['i'] < self::ITERATIONS) {
      throw new Exception('Server requested less than '.self::ITERATIONS.' iterations ('.$pairs['i'].')');
    }

    $c2wop = 'c=' . base64_encode($gs2) . ',r=' . $pairs['r'];
    $message = $c1b . ',' . $first['payload'] . ',' . $c2wop;
    
    $salted = hash_pbkdf2(
      self::ALGORITHM, 
      md5($username.':mongo:'.$password), 
      base64_decode($pairs['s']), 
      (int)$pairs['i'], 
      0, 
      true
    );
    
    // RFC Page 6
    $client = hash_hmac(
      self::ALGORITHM, 
      'Client Key', 
      $salted, 
      true
    );
    
    $server = hash_hmac(
      self::ALGORITHM, 
      'Server Key', 
      $salted, 
      true
    );
    
    $signature = hash_hmac(
      self::ALGORITHM, 
      $message, 
      sha1($client, true), 
      true
    );

    $next= yield [
      'saslContinue'   => 1,
      'payload'        => new Bytes($c2wop.',p='.base64_encode($client ^ $signature)),
      'conversationId' => $first['conversationId'],
      '$db'            => $source,
    ];

    $signature = hash_hmac('sha1', $message, $server, true);

    if (base64_decode($pairs['v']) !== $signature) 
    {
      throw new Exception('Server validation failed '.base64_encode($signature).' ('.$pairs['v'].')');
    }

    yield [
      'saslContinue'   => 1,
      'payload'        => '',
      'done'           => true,
      'conversationId' => $next['conversationId'],
      '$db'            => $source,
    ];
  }
}