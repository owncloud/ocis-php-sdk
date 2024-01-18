#!/bin/sh
set -e

# init ocis
ocis init

if [ "$WITH_WRAPPER" = "true" ]; then
    ociswrapper serve --bin=ocis --url "$OCIS_URL"
else
    ocis server
fi
