#!/usr/bin/env bash
pm2 delete sd -s || :
cd /home/ubuntu/sd-ec2/back
eval $([ -r "/var/www/.env" ] && cat "/var/www/.env") pm2 start npm --name sd --time -- start