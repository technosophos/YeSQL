<?php

require_once 'PHPUnit/Framework.php';

class YeSQLTest extends PHPUnit_Framework_TestCase {
  
  public function setUp() {
    $dsn = 'sqlite:./test.sq3';
    $this->pdo = new PDO($dsn);
    
    $this->pdo->execute(YeSQL::schema());
  }
  
  public function tearDown() {
    // TODO: Unlink the DB.
  }
  
  public function testConstructor() {
    
  }
  
  public function testSchema() {
    
  }
  
  public function testSave() {
    
  }
  
  public function testFind() {
    
  }
}