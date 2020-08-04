<html>
<title>Manual Check</title>
<!--<body bgcolor="yellow"></body>-->
<body>
<style>
a {
    color: inherit;
    text-decoration: none;
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
<h1> Check Manually |  <a href=../all_buckets.php>All Buckets</a></h1>
</div>
<br>

<form name="manualcheck" method="POST"  action="process.php">
<input type="text" class="newcls" name="bucketname" pattern="[a-z0-9-.]{3,63}" placeholder="Enter Bucket Name..." maxlength="63" size="25" required="required">
<input type="hidden" class="newcls" name="sourceurl" value="-">
<input type="hidden" class="newcls" name="hostname" value="-">
<input type="submit" class="newcls" value="Check Bucket">
</form>
</body>
</html>