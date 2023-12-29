# mounting 'ociswrapper' binary doesn't work with image 'amd64/alpine:3.17' (busybox based)

ARG OCIS_IMAGE
FROM ${OCIS_IMAGE:-owncloud/ocis:latest} as ocis

FROM ubuntu:22.04
RUN apt update && apt install -y curl

COPY --from=ocis /usr/bin/ocis /usr/bin/ocis

COPY ["./docker/ocis/serve-ocis.sh", "/usr/bin/serve-ocis"]
RUN chmod +x /usr/bin/serve-ocis

EXPOSE 9200
EXPOSE 5200

ENTRYPOINT [ "serve-ocis" ]
