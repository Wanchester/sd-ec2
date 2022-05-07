#!/usr/bin/env bash
git config --global --add safe.directory "/home/ubuntu/sd-ec2"
cd /home/ubuntu/sd-ec2
git reset --hard
git pull
find . -name "**/*.sh" -exec chmod a+x {} \;
./scripts/hash.sh "/home/ubuntu/sd-ec2" > "/var/www/portal_hash.txt"
ansible-playbook playbook.yml