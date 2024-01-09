#!/bin/bash

. .drone.env

function validateCommand(){
    ocis_cache=$(mc find s3/$1/ocis-build/$2/ocis 2>&1 | grep 'Object does not exist')
    if [[ "$ocis_cache" != "" ]]
    then
        echo "$2 doesn't exist"
        exit 0
    fi
}
validateOcisCommitId $CACHE_BUCKET $OCIS_STABLE_COMMITID
validateOcisCommitId $CACHE_BUCKET $OCIS_COMMITID
exit 78