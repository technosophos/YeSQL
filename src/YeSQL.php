<?php
/** @file
 * YeSQL NoSQL-like database library.
 */

require_once 'YeSQLCursor.php';

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
   * Insert a record into the data storage.
   *
   * This will index the record and perform any necessary indexing as well.
   *
   * @param array &$record
   *  The record to insert. This is passed by reference, and the attribute 'id' is 
   *  set for you.
   * @return boolean
   *  TRUE if the insert succeeded.
   * @throws YeSQLException
   *  thrown if the insertion operation could not complete.
   */
  public function insert(array &$record) {
    if (isset($record['id'])) {
      throw new YeSQLException('Object has ID and cannot be inserted.');
    }
    
    $time = $_SERVER['REQUEST_TIME'];
    $uid = $this->generateID();
    
    // Set the ID before we save.
    $record['id'] = $uid;
    
    $insert_entities = $this->db->prepare('INSERT INTO entities 
      (id, updated, body) 
      VALUES (:uid, DATETIME("now"), :body)');
      
    $insert_attributes = $this->db->prepare('INSERT INTO attributes
      (id, akey, avalue, ahash)
      VALUES (:uid, :key, :value, :crc)');
      
    if (empty($insert_entities) || empty($insert_attributes)) {
      throw new YeSQLException('Could not prepare insert statements.');
    }
    
    // Transform attributes into INSERT data.
    $attributes = $this->prepareIndexes($record, $uid);
    
    // Begin transaction
    $this->db->beginTransaction();
    
    // Insert main value
    if (!$insert_entities->execute(array(':uid' => $uid, ':body' => serialize($record)))) {
      $this->db->rollBack();
      throw new YeSQLException('Failed to store primary object.');
    };
    
    // Insert all referencing index values
    foreach ($attributes as $stmt_data) {
      if (!$insert_attributes->execute($stmt_data)) {
        $this->db->rollBack();
        throw new YeSQLException('Failed to store index for ' . $key);
      }
    }
    
    // End transaction
    $this->db->commit();
    return TRUE;
  }
  
  public function update(array &$record) {
    if (empty($record['id'])) {
      throw new YeSQLException('No id set; cannot find the record to update.');
    }
    
    $uid = $record['id'];
    
    // Delete old attributes.
    $stmt = $this->db->prepare('DELETE FROM attributes WHERE id = :id');
    $stmt->execute(array(':id' => $uid));
    
    // Update main record.
    $update_entities = $this->db->prepare('UPDATE entities 
      SET updated = DATETIME("now"), body = :body
      WHERE id = :uid');
    
    // Insert the attributes
    $insert_attributes = $this->db->prepare('INSERT INTO attributes
      (id, akey, avalue, ahash)
      VALUES (:uid, :key, :value, :crc)');
      
    if (empty($update_entities) || empty($insert_attributes)) {
      throw new YeSQLException('Could not prepare update/insert statements.');
    }
    
    // Transform attributes into INSERT data.
    $attributes = $this->prepareIndexes($record, $uid);
    
    // Begin transaction
    $this->db->beginTransaction();
    
    // Insert main value
    if (!$update_entities->execute(array(':uid' => $uid, ':body' => serialize($record)))
        || $update_entities->rowCount() == 0) {
      $this->db->rollBack();
      throw new YeSQLException('Failed to store primary object.');
    };
    
    // Insert all referencing index values
    foreach ($attributes as $stmt_data) {
      if (!$insert_attributes->execute($stmt_data)) {
        $this->db->rollBack();
        throw new YeSQLException('Failed to store index for ' . $key);
      }
    }
    
    // End transaction
    $this->db->commit();
    return TRUE;
    
  }
  
  /**
   * Save a record.
   */
  public function save(array &$record) {
    if (!empty($record['id'])) {
      // We have an existing object
      return $this->update($record);
    }
    else {
      return $this->insert($record);
    }
  }
  
  public function delete($query = NULL) {
    if (!isset($query)) {
      $delAttrs = $this->db->exec('DELETE FROM attributes');
      $delEnt = $this->db->exec('DELETE FROM entities');
    }
  }
  
  /**
   * Find a record.
   */
  public function find($query = array()) {
    return new YeSQLCursor($this->db, $query);
  }

  public function generateID() {
    $res = $this->db->query('SELECT LOWER(HEX(RANDOMBLOB(16))) AS uuid');
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
   * This will return an array that looks like this:
   *
   * @code
   * <?php
   *  array(
   *    array(
   *      ':uid' => '1234123412341234', 
   *      ':key' => 'foo.bar', 
   *      ':value' => 'I am the value', 
   *      ':crc' => 01231,
   *   ),
   *    array(
   *      ':uid' => '1234123412341234', 
   *      ':key' => 'foo.bar', 
   *      ':value' => 'I am the value', 
   *      ':crc' => 01231,
   *   ),
   * )
   * ?>
   * @endcode
   *
   * @param array $data
   *  An array of primitive data (arrays, scalars).
   * @return array
   *  An associative array of keys and values.
   */
  protected function prepareIndexes(array $data, $uid, $prefix = '', &$buffer = array()) {
    
    foreach ($data as $k => $v) {
      // Cast objects into arrays. This will not always work... nor should it.
      if (is_object($v)) {
        $v = (array)$v;
      }
      
      // Skip ID attribute. No point in indexing it.
      // The point: CONSISTENCY. Makes queries much easier to write, reduces special case logic.
      // if (empty($prefix) && $k == 'id') {
      //         continue;
      //       }
      
      if (is_array($v)) {
        // Don't index empty arrays.
        if (empty($v)) {
          continue;
        }
        // Indexed arrays are stored as sets of k/v pairs.
        elseif(isset($v[0])) {
        //  throw new Exception('NOT IMPLEMENTED');
          foreach ($v as $subval) {
            $newkey = $prefix . $k;
            $buffer[] = array(
              ':uid' => $uid, 
              ':key' => $newkey, 
              ':value' => $subval, 
              ':crc' => crc32($newkey),
            );
          }
        }
        // Else we store special dot-suffixed keys.
        else {
          // Recurse.
          $this->prepareIndexes($v, $uid, $k . '.', $buffer);
        }
      }
      else {
        $newkey = $prefix . $k;
        $buffer[] = array(
          ':uid' => $uid, 
          ':key' => $newkey, 
          ':value' => $v, 
          ':crc' => crc32($newkey),
        );
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
  ahash NUMERIC
  -- FOREIGN KEY id REFERENCES entities (id)
  -- KEY (akey, avalue),
  -- KEY (akey, ahash),
);';
  }
}

class YeSQLException extends Exception {}