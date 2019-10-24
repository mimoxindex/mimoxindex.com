#!/bin/bash

CRAWLER_ROOT="/var/www/mimoxindex.com/cron/crawlers"
CRAWLER_OUTPUT="/var/www/mimoxindex.com/rssoutput"
CACHE="/mnt"
cd ${CRAWLER_ROOT}

RSSEOF="</channel>\n</rss>"

PIDS=`ps axu | grep php | grep crawler | grep -v grep | awk {'print $2'}`
kill -9 $PIDS > /dev/null 2>&1 &

sleep 1

# looping through the sites to crawl
for SITE in profession-1 cvonline jobline schonherzbazis profession-2 indeed-developer indeed-devops indeed-cloud indeed-rendszergazda workania-developer workania-devops mimox
do
  RSS_OUT=${CACHE}/${SITE}-rss.xml
  if [ -f ${RSS_OUT} ]; then
    echo "Removing previous file... $RSS_OUT"
    # removing last week's output from both places
    rm -f ${RSS_OUT}
  fi
  sleep 1

  echo "Generating new RSS file... $RSS_OUT"
  /usr/bin/php -f "crawler-${SITE}-rss.php" > ${RSS_OUT} 2>/dev/null

  # if new output is incomplete append end of RSS string to make it valid
  if ! grep -Fxq "</rss>" ${RSS_OUT}; then
    printf $RSSEOF >> ${RSS_OUT}
  fi
  
  if [ -f ${RSS_OUT} ];
  then
    cp ${RSS_OUT} ${CRAWLER_OUTPUT}/${SITE}-rss.xml
  fi
  
  sleep 5
done

exit 0
