//If you think we can improve Bucky, then suggestions are welcomed.
var sourcecode = document.documentElement.outerHTML;
var pageurl= document.URL;
var hostname= window.location.hostname;
var s3bucketurl= sourcecode.match(/https:\/\/[a-z0-9-.]{3,63}\.s3\.([a-z0-9-]{5,19}\.|)amazonaws\.com/g);
if (s3bucketurl==null)
{
var s3bucketname= sourcecode.match(/(?<=\/\/s3\.amazonaws.com\/)[a-z0-9-.]{3,63}(?=\/)/);
if (s3bucketname==null)
{
}
else
sendBucket();

}
else
{
  var s3bucketname= String(s3bucketurl[0]).match(/(?<=\/\/).*(?=\.s3)/);
  sendBucket();
}

function sendBucket() {

  var xhr = new XMLHttpRequest();
    xhr.open("POST", "http://127.0.0.1:13337/process.php", true);
    xhr.setRequestHeader("Accept-Language", "en-US,en;q=0.5");
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    var body = ("bucketname="+s3bucketname+"&sourceurl="+pageurl+"&hostname="+hostname);
    xhr.send(body);
    
}