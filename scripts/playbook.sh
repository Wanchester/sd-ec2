#!/usr/bin/env bash
LAST_FRONT_HASH=$([ -r "/var/www/front_hash.txt" ] && cat "/var/www/front_hash.txt" | sed -e "s/[[:space:]]//g")
LAST_BACK_HASH=$([ -r "/var/www/back_hash.txt" ] && cat "/var/www/back_hash.txt" | sed -e "s/[[:space:]]//g")
LAST_PORTAL_HASH=$([ -r "/var/www/portal_hash.txt" ] && cat "/var/www/portal_hash.txt" | sed -e "s/[[:space:]]//g")

git config --global --add safe.directory "/home/ubuntu/sd-ec2"
cd /home/ubuntu/sd-ec2
git reset --hard
git pull
find . -wholename "./scripts/*.sh" -exec chmod a+x {} \;
ansible-playbook playbook.yml

# Fall back on the last working version if deploy fails
ret=$?
if [ $ret -ne 0 ]; then
  echo "\n\n\nAnsible failed to deploy (code=$ret). Falling back on latest working version..."

  if [ -z "$LAST_FRONT_HASH" ] && [ -z "$LAST_BACK_HASH" ] && [ -z "$LAST_PORTAL_HASH" ]; then
    echo "\n\n\nNo working version has been found. Deploy failed."
    exit 1
  else
    echo "\n\n\nFound currently working versions. Trying to recover..."

    if ! [ -z "$LAST_PORTAL_HASH" ] then
      git reset --hard
      git checkout "$LAST_PORTAL_HASH"
    fi

    ansible-playbook playbook.yml --extra-vars "front_hash='$LAST_FRONT_HASH' back_hash='$LAST_BACK_HASH'"
  fi

  # If failed to fall back, report
  ret=$?
  if [ $ret -ne 0 ]; then
    echo "\n\n\nCould not fall back. Deploy failed."
    exit 1
  fi
fi

# Change the owner of the public folder
chown -R $USER:$USER "/var/www"

./scripts/hash.sh "/home/ubuntu/sd-ec2" > "/var/www/portal_hash.txt"

echo "\n\n\nDeploy succeeded."