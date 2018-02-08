#!/usr/bin/env bash

WORK_DIR=`mktemp -d`
TARGET_DIR=`git rev-parse --show-toplevel`

function die {
    echo $1
#    rm -rf ${WORK_DIR}
    exit;
}

git status || die 'Run script from target repository folder'

if test "$#" -ne 4; then
    echo "Usage: git-import.sh <source repository> <source path> <source branch> <target path>"
    echo "Example: git-import.sh git@bitbucket.org:mediabankpam/pam.git pamseeddata master data"
    die 'Invalid params'
fi

SOURCE_REPO=$1
SOURCE_PATH=$2
SOURCE_BRANCH=$3
TARGET_PATH=$4

#prepare donor repository
git clone ${SOURCE_REPO} ${WORK_DIR}/source || die 'Can`t clone source repository';
cd ${WORK_DIR}/source
git checkout ${SOURCE_BRANCH}
if [ ! -e ${WORK_DIR}/source/${SOURCE_PATH} ]; then
    die 'Path not found in source repository';
fi

git remote rm origin || die "Can't remove remote origin from source repository"
FILES=`ls -A $WORK_DIR/source/$SOURCE_PATH`
git filter-branch --subdirectory-filter ${SOURCE_PATH} -- --all || die "Can't filter source tree"
mkdir -p ${TARGET_PATH} || die "Can't create target repository path in source repository"
for FILE in $FILES
do
    cp -r $FILE ${TARGET_PATH}
    git rm -r $FILE
done
git add ${TARGET_PATH} || die "Can't add target path to source repository changes"
git commit -m "git-import $SOURCE_REPO/$SOURCE_PATH to $TARGET_DIR" || "Can't commit changes to source repository"
pwd

#import files to current repository
cd ${TARGET_DIR} || die "Can't change dir to target repository root"
git remote add source.donor ${WORK_DIR}/source  || die 'Can`t clone source repository';

echo
echo ready to merge
echo

git pull source.donor ${SOURCE_BRANCH}  --allow-unrelated-histories || die "Import failed"
git remote rm source.donor || die "Cleanup failed"

die "Success"