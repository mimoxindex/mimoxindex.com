#!/bin/bash

if [ ! -d /mnt/webcache ];
then
        echo "Make webcache..."
        mkdir -p /mnt/webcache > /dev/null 2>&1 
        chmod 777 /mnt/webcache/
fi

if [ ! -d /mnt/weblog ];
then
        echo "Make weblog..."
        mkdir -p /mnt/weblog > /dev/null 2>&1
        chmod 777 /mnt/weblog/
fi
