
![Bucky](https://github.com/smaranchand/bucky/blob/master/bucky.gif?raw=true)

# Bucky
Bucky is an automatic tool designed to discover S3 bucket misconfiguration, Bucky consists up of two parts Bucky firefox addon and Bucky backend engine. Bucky addon reads the source code of the webpages and uses Regular Expression(Regex) to match the S3 bucket used as Content Delivery Network(CDN)  and sends it to the Bucky Backend engine. The backend engine receives the data from addon and checks if the S3 bucket is publicly writeable or not.


# Working
Bucky addon sends the details of s3 bucket name discovered from a user visited web pages to backend engine.
It uses [AWS PHP SDK](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/getting-started_installation.html) to discover misconfiguration.
Users can also check for S3 bucket misconfiguration manually. All the results from automatic and manuall check are populated to dashboard.

# Installation

```
git clone https://github.com/smaranchand/bucky.git
cd bucky

```

Prerequisites: AWS Key Pairs and PHP installation 

Get AWS Access Keys: https://console.aws.amazon.com/iam/home?#/security_credentials

PHP installation: Install according to your OS,  apt install php7/ brew install php


Currently, Bucky addon is not published in the Firefox addon store; as soon as the addon will be published, the addon link will be provided.

For now, users can  manually load the addon into the browser to do so 

1. Go to Firefox and visit  about:debugging
2. Click on "This Firefox" > Load Temporary Add-on
3. Select the addon  located at bucky/addon/bucky.js

Add AWS Access keys:
```
cd bucky/
nano config.inc.php 
Add your AWS Access Key ID and Secret Access Key. (On-Line 57 and 61)
```


# Usage

To use Bucky, load the Bucky addon to the browser and start backend engine.
```
cd bucky/
chmod +x run.sh
./run.sh

The backend engine runs on http://127.0.0.1:13337
Browse websites, Bucky will discover S3 buckets automatically and will be reflected in the dashboard.
Visit the above address to access Bucky dashboard.
```
