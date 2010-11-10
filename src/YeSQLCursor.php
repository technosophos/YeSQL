<?php
class YeSQLCursor {
  
  // A PDOStatement for a SELECT query.
  protected $stmt;
  protected $substitutions;
  protected $results = NULL;
  protected $query = array();
  protected $count = 0;
  protected $db = NULL;
  
  /**
   * Build a new cursor.
   *
   * Not that the cursor has not been executed yet.
   */
  public function __construct(PDO $db, array $query) {
    $this->db = $db;
    $this->query = $query;
  }
  
  public function count() {
    $this->execute();
    //return count($this->results);
    return $this->count;
  }
  
  /**
   * This will execute the query ONCE.
   *
   * If you want to re-execute the same query, you must reset() first.
   *
   * @code
   * <?php
   *  $cursor = $yesql->find($stuff);
   *  $cursor->execute(); // Does the query
   *  $cursor->execute(); // Does nothing
   *  $cursor->reset()
   *  $cursor->execute(); // Does the query again.
   * ?>
   * @endcode
   */
  protected function execute() {
    if (!isset($this->results)) $this->executeFindQuery();
  }
  
  public function reset() {
    // Allows the query to be run again.
    $this->results = NULL;
    $this->count = 0;
  }
  
  protected function executeFindQuery() {
    $resultsBuffer = array();
    $subselectBuffer = array();
    $qcounter = 0;
    $selcounter = 0;
    
    // We use the CRC because it is fixed length int, and is fast on lookups. The optimizer can
    // work with this, we assume. (The MySQL one does.)
    $subqueryTemplate = 'SELECT id FROM attributes 
      WHERE akey = ? AND ahash = ? AND avalue = ?';
    
    foreach ($this->query as $k => $v) {
      if (is_array($v) || strpos($v, '$') === 0) {
        throw new YeSQLException('Not supported yet.');
      }
      else {
        // Attempt to build a select subquery:
        $subselectBuffer[] = $k;
        $subselectBuffer[] = crc32($v);
        $subselectBuffer[] = $v;
        ++$qcounter;
        ++$selcounter;
      }
    }
    
    $baseQuery = 'SELECT entities.body FROM entities 
      INNER JOIN attributes ON entities.id = attributes.id';
      
    if ($qcounter > 0) {
      throw new YeSQLException('Not implemented.');
      $baseQuery += ' WHERE ';
      // Add where clauses...
      
      //$this->db
    }
    else {
      $stmt = $this->db->query($baseQuery);
    }
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $this->count++;
      $resultsBuffer[] = unserialize($row['body']);
    }
    $this->results = $resultsBuffer;
    return TRUE;
  }
}