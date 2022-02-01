<?php

require_once __DIR__ . '/scram.php';

class Auth
{
  //https://docs.mongodb.com/manual/core/security-scram/

  public static function mechanism()
  {
    return new Scram();
  }
}