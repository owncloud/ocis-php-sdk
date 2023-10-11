FROM ubuntu:22.04 AS dependencies

RUN apt update && apt install -y openssl wget && \
    mkdir -p /certs && cd /certs &&\
    openssl req -x509 -newkey rsa:2048 \
    -keyout keycloak.key \
    -out keycloak.crt \
    -sha256 -days 365 -batch -nodes && \
    mv keycloak.key keycloak.pem && \
    cat keycloak.crt >> keycloak.pem && \
    wget https://github.com/moparisthebest/static-curl/releases/download/v8.4.0/curl-amd64 -O /tmp/curl && \
    # download a static cURL binary and check its SHA sum
    if [ "`sha256sum /tmp/curl | cut -d" " -f1`" != "f19a1ca90973ee955ae8e933f10158b60e8b5a7ed553d099d119e1e2bafc4270" ]; then echo "SHA sum of cURL binary not valid"; exit 1; fi


FROM quay.io/keycloak/keycloak:22.0.4

USER root
COPY --from=dependencies /certs /opt/keycloak/certs
COPY --from=dependencies /tmp/curl /opt/curl
RUN chown -R keycloak:root /opt/keycloak/certs
RUN chmod +x /opt/curl

USER keycloak

EXPOSE 8080
EXPOSE 8443

ENTRYPOINT [ "/opt/keycloak/bin/kc.sh" ]

CMD [ \
    "start", \
    "--proxy edge", \
    "--https-certificate-file=/opt/keycloak/certs/keycloak.crt", \
    "--https-certificate-key-file=/opt/keycloak/certs/keycloak.pem", \
    "--spi-connections-http-client-default-disable-trust-manager=true", \
    "--import-realm", \
    "--health-enabled=true" \
    ]
