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
    if ($record['id']) {
      // We have an existing object
    }
    else {
      // We need to get an ID.
      //$this->db->exec();
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
    
    // uh... UUID generator, anyone?
    $dbType = 'sqlite';
    
    switch ($dbType) {
      case 'sqlite':
        
      case 'mysql':
        // SELECT UUID()
      default:
        // From drupal.org/project/uuid
        return sprintf('%04x%04x-%04x-%03x4-%04x-%04x%04x%04x',
          // 32 bits for "time_low".
          mt_rand(0, 65535), mt_rand(0, 65535),
          // 16 bits for "time_mid".
          mt_rand(0, 65535),
          // 12 bits before the 0100 of (version) 4 for "time_hi_and_version".
          mt_rand(0, 4095),
          bindec(substr_replace(sprintf('%016b', mt_rand(0, 65535)), '01', 6, 2)),
          // 8 bits, the last two of which (positions 6 and 7) are 01, for "clk_seq_hi_res"
          // (hence, the 2nd hex digit after the 3rd hyphen can only be 1, 5, 9 or d)
          // 8 bits for "clk_seq_low" 48 bits for "node".
          mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535)
        );
    }
    
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
  id TEXT NOT NULL,
  akey TEXT NOT NULL,
  avalue TEXT,
  ahash TEXT
  -- KEY (akey, avalue),
  -- KEY (akey, ahash),
);';
  }
}
