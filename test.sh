#!/bin/bash

# variables
WORKING_PATH="/home/hero/processor"
TARGET_BRANCH="origin/main"

# goto folder
cd -P "$WORKING_PATH"

# before
before=$(git rev-parse $TARGET_BRANCH)

# git pull from latest repo
git pull ${TARGET_BRANCH//\// }

after=$(git rev-parse $TARGET_BRANCH)

if [ "$before" != "$after" ]; then
    # restart webman
    echo "restart"
fi