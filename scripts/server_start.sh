#!/usr/bin/env bash
pm2 delete sd -s || :
eval $([ -r "/var/www/.env" ] && cat "/var/www/.env") pm2 start npm --name sd --time -- start