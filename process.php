<?php
//I admit that i am a noob programmer, if you do have suggestions to improve Bucky,  lets talk https://twitter.com/smaranchand
function bucketsuccess(){
    $newbucket=$_POST['bucketname'];
    $sourceurl=$_POST['sourceurl'];
    $hostname=$_POST['hostname'];
    $serverip= gethostbyname("$hostname"); 
    
    class MyDB extends SQLite3 {
        function __construct() {
           $this->open('bucky.db');
        }
     }
     
     $db = new MyDB();
     if(!$db){
        echo $db->lastErrorMsg();
     } else {
     }
  
     $sql =<<<EOF
        INSERT INTO BUCKY (BUCKETNAME,URL,IP,POC,VULNERABLE)
        VALUES ('$newbucket', '$sourceurl', '$serverip', 'https://$newbucket.s3.amazonaws.com/bucky.txt', '<b>YES</b>' );
  
  EOF;
  
     $ret = $db->exec($sql);
     if(!$ret) {
        echo $db->lastErrorMsg();
     } else {
   
   header("refresh:3; url=all_buckets.php");
   echo "S3 Bucket <b>$newbucket</b> seems vulnerable, Check dashboard for more information.";
     
     }
     $db->close();
}

function bucketfailed(){
    $newbucket=$_POST['bucketname'];
    $sourceurl=$_POST['sourceurl'];
    $hostname=$_POST['hostname'];
    $serverip= gethostbyname("$hostname"); 
    class MyDB extends SQLite3 {
        function __construct() {
           $this->open('bucky.db');
        }
     }
     
     $db = new MyDB();
     if(!$db){
        echo $db->lastErrorMsg();
     } else {
     }
  
     $sql =<<<EOF
        INSERT INTO BUCKY (BUCKETNAME,URL,IP,POC,VULNERABLE)
        VALUES ('$newbucket', '$sourceurl', '$serverip', '-', 'NO' );
  
  EOF;
  
     $ret = $db->exec($sql);
     if(!$ret) {
        echo $db->lastErrorMsg();
     } else {
      header("refresh:3; url=manual_check.php");
      echo "S3 Bucket <b>$newbucket</b> doesnot seems vulnerable, Check dashboard for more information.";
     }
     $db->close();
}

$newbucket=$_POST['bucketname'];
error_reporting(0);
require_once 'sdk.class.php';
$s3 = new AmazonS3();
$response = $s3->create_object($newbucket, 'bucky.txt', array(
'contentType' => 'text/plain',
'body' => 'S3 bucket misconfiguration is discovered.
Discovered by Bucky.
Developed by: https://twitter.com/smaranchand',
'acl'=>AmazonS3::ACL_PUBLIC,
));
if ((int) $response->isOK()) 
bucketsuccess();
else
bucketfailed();
?>
