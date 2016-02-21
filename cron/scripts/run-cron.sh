#!/bin/bash

/var/www/mimoxindex.com/cron/scripts/run-crawlers.sh > /mnt/crawler.log 2>&1
/var/www/mimoxindex.com/invoke-mimoxindex.sh >> /mnt/weblog/mimoxindex.log 2>&1
/var/www/mimoxindex.com/invoke-mimoxindex.sh trendcount >> /mnt/weblog/mimoxindex.log 2>&1
rm -f /mnt/webcache/mimoxindex-cache.html*
nohup /var/www/mimoxindex.com/invoke-mimoxindex-web.sh >> /mnt/weblog/mimoxindex-ui.log 2>&1 &
