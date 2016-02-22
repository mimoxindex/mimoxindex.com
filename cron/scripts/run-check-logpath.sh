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

if [ ! -f /mnt/swapfile ];
then
	fallocate -l 2048M /mnt/swapfile
	chmod 600 /mnt/swapfile
	mkswap /mnt/swapfile
	swapon /mnt/swapfile
fi
