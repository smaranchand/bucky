
![Bucky](https://github.com/smaranchand/bucky/blob/master/bucky.gif?raw=true)

# Bucky
Bucky is an automatic tool designed to discover S3 bucket misconfiguration, Bucky consists up of two modules Bucky firefox addon and Bucky backend engine. Bucky addon reads the source code of the webpages and uses Regular Expression(Regex) to match the S3 bucket used as Content Delivery Network(CDN)  and sends it to the Bucky Backend engine. The backend engine receives the data from addon and checks if the S3 bucket is publicly writeable or not. Bucky automatically uploads a text file as Proof Of Concept(PoC) if the bucket is vulnerable.


# Working
Bucky addon sends the details of s3 bucket name discovered from a user visited web pages to backend engine.
It uses [AWS PHP SDK](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/getting-started_installation.html) to discover misconfiguration.
Users can also check for S3 bucket misconfiguration manually. All the results from automatic and manuall check are populated to dashboard.

Checkout video https://vimeo.com/444442588

# Installation

```
git clone https://github.com/smaranchand/bucky.git
cd bucky

```

Requirements: AWS Access Keys and PHP installation 

Get AWS Access Keys: https://console.aws.amazon.com/iam/home?#/security_credentials

PHP installation: Install according to your OS,  apt install php7 / brew install php


Currently, Bucky addon is not published in the Firefox addon store; as soon as the addon will be published, the addon link will be provided.

For now, users can  manually load the addon into the browser to do so 

1. Open Firefox browser and visit about:debugging
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

# Screenshots
Running Bucky

![run_bucky](https://github.com/smaranchand/bucky/blob/master/scr/run_bucky.png?raw=true)

Loading Addon

![load_addon](https://github.com/smaranchand/bucky/blob/master/scr/bucky_addon.png?raw=true)
User Interface

![dashboard](https://github.com/smaranchand/bucky/blob/master/scr/dashboard_loading.png?raw=true)

All Buckets

![all_buckets](https://github.com/smaranchand/bucky/blob/master/scr/all_buckets.png?raw=true)
Manual Check

![manual_check](https://github.com/smaranchand/bucky/blob/master/scr/manual_check.png?raw=true)

POC By Bucky 

![Bucky_POC](https://github.com/smaranchand/bucky/blob/master/scr/vulnerable_poc.png?raw=true)



# Note
Bucky is not a perfect tool to discover S3 buckets, it is well known that Bucky lacks many feautres and it  may fail to detect the misconfiguration sometimes. Certain changes and  development are in pipeline. I really appreciate the feedbacks and contribution.




