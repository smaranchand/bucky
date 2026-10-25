#!/bin/bash
#Bucky is AWS S3 bucket discovery tool developed by  https://twitter.com/smaranchand
#Bucky Backend Engine automatically  takes input from Bucky addon and check for misconfiguration.
set -euo pipefail
cd "$(dirname "$0")"

if [ ! -d vendor ]; then
  echo "Dependencies missing. Run 'make install' first." >&2
  exit 1
fi
if [ ! -f .env ]; then
  echo "No .env found. Copy .env.example to .env and set your credentials." >&2
  exit 1
fi

echo "
  ____             _                  __   ___  
 |  _ \           | |                /_ | / _ \ 
 | |_) |_   _  ___| | ___   _   __   _| || | | |
 |  _ <| | | |/ __| |/ / | | |  \ \ / / || | | |
 | |_) | |_| | (__|   <| |_| |   \ V /| || |_| |
 |____/ \__,_|\___|_|\_\\__, |    \_/ |_(_)___/ 
                         __/ |                  
                        |___/     
An automatic tool to find misconfigured AWS S3 buckets.  
Developed by : https://twitter.com/smaranchand          
";
php -S 127.0.0.1:13337 -t public/ public/index.php
