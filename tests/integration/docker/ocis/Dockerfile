# mounting 'ociswrapper' binary doesn't work with image 'amd64/alpine:3.17' (busybox based)

ARG OCIS_IMAGE
FROM ${OCIS_IMAGE:-owncloud/ocis:latest} as ocis

COPY --chmod=0755 ./docker/ocis/serve-ocis.sh /usr/bin/serve-ocis

EXPOSE 9200
EXPOSE 5200

ENTRYPOINT [ "serve-ocis" ]
