<?php

class OpCode {
  const REPLY = 1;
  const MSG = 1000;
  const UPDATE = 2001;
  const INSERT = 2002;
  const QUERY = 2004;
  const GET_MORE = 2005;
  const DELETE = 2006;
  const KILL_CURSORS = 2007;
  const COMPRESSED = 2012;
  const RESERVED = 2003;
}

