<?php
//This script creates SQLite3 database and add tables.
   class MyDB extends SQLite3 {
      function __construct() {
         $this->open('bucky.db');
      }
   }
   $db = new MyDB();
   if(!$db) {
      echo $db->lastErrorMsg();
   } else {
   $sql =<<<EOF
      CREATE TABLE BUCKY
      (ID INTEGER PRIMARY KEY AUTOINCREMENT,
      BUCKETNAME           VARCHAR    NOT NULL,
      URL                  VARCHAR    NOT NULL,
      IP            VARCHAR     NOT NULL,
      POC        VARCHAR     NOT NULL,
      VULNERABLE         VARCHAR);
EOF;

   $ret = $db->exec($sql);
   if(!$ret){
      echo $db->lastErrorMsg();
   } else {
      echo "Database setup successful.";
   }
   $db->close();
}
?>
