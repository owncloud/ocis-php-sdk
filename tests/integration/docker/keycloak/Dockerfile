FROM ubuntu:22.04 AS gen-certs

RUN apt update && apt install -y openssl && \
    mkdir -p /certs && cd /certs &&\
    openssl req -x509 -newkey rsa:2048 \
    -keyout keycloak.key \
    -out keycloak.crt \
    -sha256 -days 365 -batch -nodes && \
    mv keycloak.key keycloak.pem && \
    cat keycloak.crt >> keycloak.pem

FROM quay.io/keycloak/keycloak:22.0.1

USER root
COPY --from=gen-certs /certs /opt/keycloak/certs
RUN chown -R keycloak:root /opt/keycloak/certs

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
    "--import-realm" \
    ]