<?php

require_once 'PHPUnit/Framework.php';

class YeSQLTest extends PHPUnit_Framework_TestCase {
  
  public function setUp() {
    $dsn = 'sqlite:./test.sq3';
    $this->pdo = new PDO($dsn);
  }
  
  public function testConstructor() {
    
  }
  
}