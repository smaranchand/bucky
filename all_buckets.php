<!DOCTYPE html>
<html>
<title>All Buckets</title>
<style>
a {
    color: inherit;
    text-decoration: none;
}
table {
  font-family: arial, sans-serif;
  border-collapse: collapse;
  width: 100%;
}

td, th {
  border: 2px solid #0dbce7;
  text-align: left;
  padding: 8px;
}

.newcls{
   
 
 background-color:#0dbce7;
  
 border: #2e6da4;
  
 font-family: Arial, Helvetica,  sans-serif;
  
 font-size: 15px;
  
 color: #fff;
  
 letter-spacing: 1px;
  
 padding: 8px 12px;
  
 font-size: 14px;
  
 font-weight: normal;
  
 border-radius: 4px;
  
 line-height: 1.5;
  
 text-decoration:none
  
 }
</style>

<div class="newcls">

<h1> All Buckets |  <a href=../manual_check.php>Check Manually</a></h1></div>
<br>
<table border=1>
<tr>
<th>SN</th>
<th>Bucket Name</th>
<th>Source URL</th>
<th>IP</th>
<th>POC</th>
<th>VULNERABLE</th>
</tr>
<?php
   class MyDB extends SQLite3 {
      function __construct() {
         $this->open('bucky.db');
      }
   }
   
   $db = new MyDB();
   if(!$db) {
      echo $db->lastErrorMsg();
   } else {

   }

   $sql =<<<EOF
      SELECT * from BUCKY;
EOF;

   $ret = $db->query($sql);
   while($row = $ret->fetchArray(SQLITE3_ASSOC) ) {
      echo "<tr>";
      echo "<td>". $row['ID'] . "</td>";
      echo "<td>". $row['BUCKETNAME'] . "</td>";
      echo "<td>". $row['URL'] ."</td>";
      echo "<td>". $row['IP'] ."</td>";
      echo "<td><a>". $row['POC'] ."</a></td>";
      echo "<td>".$row['VULNERABLE'] ."</td>";
   }
   $db->close();
?>