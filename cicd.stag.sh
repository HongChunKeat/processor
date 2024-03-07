#!/bin/bash

# Define a variable to store the container path
WORKING_PATH="/home/hero/processor"
CONTAINER_PATH="/var/www/processor"
CONTAINER_NAME="sw-pm2processor"
TARGET_BRANCH="origin/main"

# goto folder
cd -P "$WORKING_PATH"

# before
before=$(git rev-parse $TARGET_BRANCH)

# git pull from latest repo
git pull ${TARGET_BRANCH//\// }

# after
after=$(git rev-parse $TARGET_BRANCH)

if [ "$before" != "$after" ]; then
    # restart webman
    docker-compose restart webman-processor
fi