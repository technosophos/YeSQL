<?php

require_once 'PHPUnit/Framework.php';
require_once 'src/YeSQL.php';

class YeSQLTest extends PHPUnit_Framework_TestCase {
  
  public function setUp() {
    $dsn = 'sqlite:./test.sq3';
    $this->pdo = new PDO($dsn);
    $this->pdo->exec('DROP TABLE entities; DROP TABLE attributes;');
    $this->pdo->exec(YeSQL::schema());
    $err = $this->pdo->errorInfo();
    if ($err[1] > 0) {
      throw new Exception('Schema Failed:' . print_r($err, TRUE));
    }
    
   // throw new Exception('Schema Failed: ' . print_r($this->pdo->errorInfo(), TRUE));
  }
  
  public function tearDown() {
    // TODO: Unlink the DB.
    //$this->pdo->exec('DROP TABLE entities; DROP TABLE attributes;');
  }
  
  public function testConstructor() {
    $yes = new YeSQL($this->pdo);
    
    // Uh... what should be tested?
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
  
  public function testInsert() {
    // Test insert
    $a = array('a' => 'salad', 'b' => 'viking', 'c' => array('hot', 'dog'));
    $y = new YeSQL($this->pdo);
    $retval = $y->insert($a);
    
    $this->assertTrue($retval);
    
    $this->assertEquals(32, strlen($a['id']));
    
    $uid = $a['id'];
    
    $res = $this->pdo->query("SELECT COUNT(*) AS c FROM entities WHERE id = '$uid'");
    $o = $res->fetchObject();
    $this->assertEquals(1, $o->c);
    
    $res = $this->pdo->query("SELECT COUNT(*) AS c FROM attributes WHERE id = '$uid'");
    $o = $res->fetchObject();
    // 5 attrs because id is an attribute.
    $this->assertEquals(5, $o->c);
    
    // Test update
  }
  
  public function testUpdate() {
    $a = array('a' => 'salad', 'b' => array('viking' => 1, 'beaver' => 2), 'c' => array('hot', 'dog'));
    $y = new YeSQL($this->pdo);
    $retval = $y->insert($a);
    
    $this->assertTrue($retval);
    
    $uid = $a['id'];
    $this->assertEquals(32, strlen($uid));
    
    $a['b']['beaver'] = 'Alligator';
    
    $this->assertTrue($y->update($a));
    
    $stmt = $this->pdo->prepare('SELECT avalue FROM attributes WHERE akey = "b.beaver" AND id = :id');
      $stmt->execute(array(':id' => $uid));
    $o = $stmt->fetchObject();
    $this->assertEquals('Alligator', $o->avalue);
  }
  
  public function testSave() {
    $obj = array(
      'kind' => 'Table',
      'legs' => 4,
      'materials' => array('top' => 'laminate', 'legs' => 'wood'),
    );
    
    $yes = new YeSQL($this->pdo);
    
    $this->assertTrue($yes->save($obj));
    $this->assertEquals(32, strlen($obj['id']));
    
    $obj['legs'] = 'four';
    
    $id = $obj['id'];
    
    $this->assertTrue($yes->save($obj));
    
    // Test insert
    $stmt = $this->pdo->prepare('SELECT COUNT(*) AS c FROM entities WHERE id = :uid');
    $stmt->execute(array(':uid' => $id));
    $ret = $stmt->fetchObject();
    $this->assertEquals(1, $ret->c);
    
    // Test update
    $stmt = $this->pdo->prepare('SELECT COUNT(*) AS c FROM attributes WHERE id = :uid');
    $stmt->execute(array(':uid' => $id));
    $ret = $stmt->fetchObject();
    $this->assertEquals(5, $ret->c);
  }
  /**
   * @expectedException YeSQLException
   */
  public function testInsertFail() {
    $obj = array(
      'kind' => 'Table',
      'legs' => 4,
      'materials' => array('top' => 'laminate', 'legs' => 'wood'),
    );
    $yes = new YeSQL($this->pdo);
    
    $this->assertTrue($yes->insert($obj));
    
    // This should throw an exception.
    $yes->insert($obj);
  }
  
  /**
   * @expectedException YeSQLException
   */
  public function testUpdateFailOnMissingId() {
    $obj = array(
      'kind' => 'Table',
      'legs' => 4,
      'materials' => array('top' => 'laminate', 'legs' => 'wood'),
    );
    $yes = new YeSQL($this->pdo);
    
    // This should throw an exception.
    $yes->update($obj);
  }
  
  /**
   * @expectedException YeSQLException
   */
  public function testUpdateFailOnBogusID() {
    $obj = array(
      'kind' => 'Table',
      'legs' => 4,
      'materials' => array('top' => 'laminate', 'legs' => 'wood'),
      'id' => 'nonexistent',
    );
    $yes = new YeSQL($this->pdo);
    
    // This should throw an exception.
    $yes->update($obj);
    
    $this->assertEquals('nonexistent', $obj['id']);
  }
  
  public function testDeleteAll() {
    $obj = array(
      'kind' => 'Table',
      'legs' => 4,
      'materials' => array('top' => 'laminate', 'legs' => 'wood'),
    );
    $yes = new YeSQL($this->pdo);
    
    $this->assertTrue($yes->insert($obj));
    
    $yes->deleteAll();
    
    $stmt = $this->pdo->query('SELECT count(*) AS c FROM entities');
    $o = $stmt->fetchObject();
    $this->assertEquals(0, $o->c);
    
    $stmt = $this->pdo->query('SELECT count(*) AS c FROM attributes');
    $o = $stmt->fetchObject();
    $this->assertEquals(0, $o->c);
  }
  
  public function testDelete() {
    $obj = array(
      'kind' => 'Table',
      'legs' => 4,
      'materials' => array('top' => 'laminate', 'legs' => 'wood'),
    );
    $yes = new YeSQL($this->pdo);
    
    $this->assertTrue($yes->insert($obj));
    
    $yes->delete(array('id' => $obj['id']));
    
    $stmt = $this->pdo->query('SELECT count(*) AS c FROM entities WHERE id = "'. $obj['id'] .'"');
    $o = $stmt->fetchObject();
    $this->assertEquals(0, $o->c);
    
    $stmt = $this->pdo->query('SELECT count(*) AS c FROM attributes WHERE id = "'. $obj['id'] .'"');
    $o = $stmt->fetchObject();
    $this->assertEquals(0, $o->c);
  }
  
  public function testFind() {
    $obj1 = array(
      'kind' => 'Dining Table',
      'legs' => 4,
      'materials' => array('top' => 'laminate', 'legs' => 'wood'),
    );
    $obj2 = array(
      'kind' => 'Dining Chair',
      'legs' => 4,
      'materials' => array('seat' => 'laminate', 'legs' => 'wood'),
    );
    $yes = new YeSQL($this->pdo);
    
    $yes->insert($obj1);
    $yes->insert($obj2);
    
    $table_uid = $obj1['id'];
    $chair_uid = $obj2['id'];
    
    $this->assertTrue(2 <= $yes->find()->count());
    
    // Search by ID.
    $this->assertEquals(1, $yes->find(array('id' => $table_uid))->count());
    
    // Search attribute
    $this->assertEquals(1, $yes->find(array('kind' => 'Dining Chair'))->count());
    
    // Search int
    $this->assertEquals(2, $yes->find(array('legs' => 4))->count());
    
    // Search nested attribute
    $this->assertEquals(2, $yes->find(array('materials.legs' => 'wood'))->count());
    
    // Search with multiple attributes
    $filter = array('kind' => 'Dining Chair', 'legs' => 4);
    $this->assertEquals(1, $yes->find($filter)->count());
  }
  
}