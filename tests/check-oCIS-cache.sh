#!/bin/bash

. .drone.env

ocis_cache=$(mc find s3/$CACHE_BUCKET/ocis-build/$OCIS_COMMITID/ocis 2>&1 | grep 'Object does not exist')

ociswrapper_cache=$(mc find s3/$CACHE_BUCKET/ocis-build/$OCIS_COMMITID/ociswrapper 2>&1 | grep 'Object does not exist')

if [[ "$ocis_cache" != "" ]] || [[ "$ociswrapper_cache" != "" ]]
then
    echo "$OCIS_COMMITID doesn't exist"
    exit 0
fi
exit 78
