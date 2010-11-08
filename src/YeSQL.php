<?php
/** @file
 * YeSQL NoSQL-like database library.
 */

class YeSQL {
  
  const UPDATE_ENT_SQL = '';
  const INSERT_ENT_SQL = '';
  const SELECT_ENT_SQL = 'SELECT id, body FROM entites WHERE id = :id';
  
  protected $db;
  protected $insert_ent, $update_ent, $select_ent;
  
  /**
   * Create a new YeSQL object.
   *
   * This needs a handle to an initialized PDO object.
   *
   * @param PDO $handle
   *  A PDO object with an open connection to a database.
   */
  public function __construct(PDO $handle) {
    $this->db = $handle;
  }
  
  /**
   * Save a record.
   */
  public function save(array $record) {
    if (!empty($record['id'])) {
      // We have an existing object
    }
    else {
      $time = $_SERVER['REQUEST_TIME']
      
      $uid = $this->generateID();
      
      $insert_entities = $this->db->prepare('INSERT INTO entities 
        (id, updated, body) 
        VALUES (:uid, DATETIME("now"), :body)');
        
      $insert_attributes = $this->db->prepare('INSERT INTO attributes
        id, akey, avalue, ahash
        VALUES (:uid, :key, :value, :crc)');
      
      // We need to get an ID.
      //$this->db->exec();
      $attributes = $this->prepareIndexes($record);
      
      // Begin transaction
      $this->db->beginTransaction();
      
      // Insert main value
      if (!$insert_entities->execute(array(':uid' => $uid, ':body' => serialize($record)))) {
        $this->db->rollbackTransaction();
        throw new YeSQLException('Failed to store primary object.')
      };
      
      // Insert all referencing index values
      foreach ($attributes as $key => $value) {
        $params = array(
          ':uid' => $uid,
          ':key' => $key,
          ':value' => $value,
          ':crc' => crc32($key),
        );
        if (!$insert_attributes->execute($params)) {
          $this->db->rollbackTransaction();
          throw new YeSQLException('Failed to store index for ' . $key);
        }
      }
      
      
      // End transaction
      $this->db->commitTransaction;
    }

  }
  
  /**
   * Find a record.
   */
  public function find(array $query) {
    
  }

  public function generateID() {
    $res = $db->query('SELECT LOWER(HEX(RANDOMBLOB(16))) AS uuid');
    $row = $res->fetch(PDO::FETCH_ASSOC);
    $uuid = $row['uuid'];
    $res->closeCursor();
    return $uuid;
  }
  
  /**
   * Transform an n-depth array of data into a list of key/value pairs.
   *
   * Limitations:
   * - If an array has $array[0] set, we assume it is an indexed (non-assoc) array.
   * - If a value given is an object, we try to cast to an array. This will obviously not work with
   *   some objects. Then again, it's not the intent of this library to be able to store any old 
   *  thing, either.
   * - Strict checking is not done on non-array values. This may result in insert/update errors if
   *   you dump a resource in here.
   * - associative arrays will have recursive keys build. Indexed arrays will not.
   *
   * @param array $data
   *  An array of primitive data (arrays, scalars).
   * @return array
   *  An associative array of keys and values.
   */
  protected function prepareIndexes(array $data, $prefix = '', &$buffer = array()) {
    foreach ($data as $k => $v) {
      // Cast objects into arrays. This will not always work... nor should it.
      if (is_object($v)) {
        $v = (array)$v;
      }
      
      if (is_array($v) && !empty($v) && !isset($v[0])) {
        // Don't index empty arrays.
        if (empty($v)) {
          continue;
        }
        // Indexed arrays are stored as sets of k/v pairs.
        //elseif(isset($v[0])) {
        //  throw new Exception('NOT IMPLEMENTED');
        //}
        // Else we store special dot-suffixed keys.
        else {
          // Recurse.
          $this->prepareIndexes($v, $k . '.', $buffer);
        }
      }
      else {
        $buffer[$prefix . $k] = $v;
      }
      
    }
    return $buffer;
  }
  
  /**
   * Emit the schema.
   *
   * Schema is based on this article: 
   * http://bret.appspot.com/entry/how-friendfeed-uses-mysql
   */
  public static function schema() {
    return 
// Based on FriendFeed's schema, adapted for SQLite3.
// Note that we rely on the implicit 'rowid' column.
'CREATE TABLE entities (
    id TEXT NOT NULL,
    updated NUMERIC CURRENT_DATETIME,
    body BLOB,
    UNIQUE (id)
    -- KEY (updated)
);
' // ENGINE=InnoDB;
.
// Very generic index:
'CREATE TABLE attributes (
  id TEXT REFERENCES entities (id),
  akey TEXT NOT NULL,
  avalue TEXT,
  ahash TEXT
  -- FOREIGN KEY id REFERENCES entities (id)
  -- KEY (akey, avalue),
  -- KEY (akey, ahash),
);';
  }
}
class YeSQLException extends Exception {}