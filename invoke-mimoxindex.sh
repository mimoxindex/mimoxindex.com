#!/bin/bash

SCRIPT_PATH="/var/www/mimoxindex/cron/scripts/mimoxindex.py"

export BASEDIR="/var/www/mimoxindex/rssoutput" 

export DBHOST="127.0.0.1"
export DBUSER=""
export DBPASS=""
export DBNAME=""

export ALTA_DBHOST=""
export ALTA_DBUSER=""
export ALTA_DBPASS=""
export ALTA_DBNAME=""

python -u $SCRIPT_PATH $@


