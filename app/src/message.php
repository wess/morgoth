<?php

class Header {
  public int $messageLength;
  public int $requestID;
  public int $responseTo;
  public int $opCode;

  public function __construct(int $messageLength, int $requestID, int $responseTo, int $opCode) {
    $this->messageLength = $messageLength;
    $this->requestID = $requestID;
    $this->responseTo = $responseTo;
    $this->opCode = $opCode;
  }

  public function toString(): string {
    return pack('VVVV', $this->messageLength, $this->requestID, $this->responseTo, $this->opCode);
  }
}

class Message {
  public Header $header;
  public int $flags;
  public array $sections;
  public int $checksum;
  
  public function __construct(Header $header, int $flags, array $sections, int $checksum = -1) {
    $this->header = $header;
    $this->flags = $flags;
    $this->sections = $sections;
    $this->checksum = $checksum;
  }
}