<?php

require_once 'PHPUnit/Framework.php';
require_once 'src/YeSQL.php';

class YeSQLTest extends PHPUnit_Framework_TestCase {
  
  public function setUp() {
    $dsn = 'sqlite:./test.sq3';
    $this->pdo = new PDO($dsn);
    
    $this->pdo->exec(YeSQL::schema());
    $err = $this->pdo->errorInfo();
    if ($err[1] > 0) {
      throw new Exception('Schema Failed:' . print_r($err, TRUE));
    }
    
   // throw new Exception('Schema Failed: ' . print_r($this->pdo->errorInfo(), TRUE));
  }
  
  public function tearDown() {
    // TODO: Unlink the DB.
    $this->pdo->exec('DROP TABLE entities; DROP TABLE attributes;');
  }
  
  public function testConstructor() {
    
  }
  
  public function testEntitiesSchema() {
    // Execute queries to see if schema works.
    $count = $this->pdo->exec('INSERT INTO entities (id, body, updated) VALUES (LOWER(HEX(RANDOMBLOB(16))), "abcd", DATETIME("now"))');
    
    if ($count == 0) {
      throw new Exception('Insert Failed: ' . print_r($this->pdo->errorInfo(), TRUE));
    }
    
    $res = $this->pdo->query('SELECT id, body, updated, rowid FROM entities');
    
    if ($res === FALSE) {
      throw new Exception('Query failed:' . print_r($this->pdo->errorInfo(), TRUE));
    }
    
    $item = $res->fetchObject();
    $this->assertEquals('abcd', $item->body);
    $this->assertEquals(32, strlen($item->id));
    $this->assertEquals(1, strlen($item->rowid));
  }
  
  public function testAttributesSchema() {
    $count = $this->pdo->exec('INSERT INTO attributes (id, akey, avalue, ahash) 
      VALUES (LOWER(HEX(RANDOMBLOB(16))), "foo", "bar", "abcd")');
      
    if ($count == 0) {
      throw new Exception('Insert Failed: ' . print_r($this->pdo->errorInfo(), TRUE));
    }
    
    $res = $this->pdo->query('SELECT id, akey, avalue, ahash FROM attributes');
    if ($res === FALSE) {
      throw new Exception('Query failed:' . print_r($this->pdo->errorInfo(), TRUE));
    }
    
    $item = $res->fetchObject();
    $this->assertEquals(32, strlen($item->id));
    $this->assertEquals('foo', $item->akey);
    $this->assertEquals('bar', $item->avalue);
    $this->assertEquals('abcd', $item->ahash);
  }
  
  public function testSave() {
    // Test insert
    $a = array('a' => 'salad', 'b' => 'viking', 'c' => array('hot', 'dog'));
    $y = new YeSQL($this->pdo);
    $retval = $y->save($a);
    
    $this->assertTrue($retval);
    $result = $y->find(array('a' => 'salad'));
    $this->assertTrue($result->size() == 1);
    // Test update
  }
  
  public function testFind() {
    
  }
  
}