<?php

class Hash {
  public static function sha1($data) {
    return sha1($data);
  }

  public static function hmacSha1($input, $key) {
    return hash_hmac("sha1", $input, $key, true); 
  }

  public static function generateSalt() {
    return base64_encode(random_bytes(24));
  }

  public static function encode($value) {
    return base64_encode($value);
  }

  public static function decode($value) {
    return base64_decode($value);
  }

  public static function hi($value, $salt, $iterations) {
    return hash_pbkdf2("sha1", $value, utf8_decode($salt), $iterations);
  }
}
