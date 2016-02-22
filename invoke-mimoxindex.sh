#!/bin/bash

SCRIPT_PATH="/var/www/mimoxindex.com/cron/scripts/mimoxindex.py"

export BASEDIR="/var/www/mimoxindex.com/rssoutput" 

export DBHOST="127.0.0.1"
export DBUSER=""
export DBPASS=""
export DBNAME=""

export ALTA_DBHOST=""
export ALTA_DBUSER=""
export ALTA_DBPASS=""
export ALTA_DBNAME=""

export GMAIL_PWD=""
export GMAIL_USER=""
export GMAIL_SMTP="smtp.gmail.com:587"

python -u $SCRIPT_PATH $@


