#!/bin/bash

SCRIPT_PATH="/var/www/mimoxindex.com/httproot/mimoxindex.bottleUI.py"

export RECAPTCHA_PRIVKEY=""
export RECAPTCHA_PUBKEY=""
export GMAIL_PWD=""
export GMAIL_USER=""
export GMAIL_SMTP="smtp.gmail.com:587"
export RUN_IP="0.0.0.0"
export RUN_PORT="11080"
export DBHOST="127.0.0.1"
export DBUSER=""
export DBPASS=""
export DBNAME=""
export TEMPLATE="/var/www/mimoxindex.com/httproot/templates/mimoxindex-template.html"
export ADMIN_TEMPLATE="/var/www/mimoxindex.com/httproot/templates/mimoxindex-admin.html"
export UPLOADS="/var/www/mimoxindex.com/httproot/uploads"
export CACHE="/mnt/webcache/mimoxindex-cache.html"

python -u $SCRIPT_PATH $@


