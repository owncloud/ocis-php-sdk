OC_CI_PHP = "owncloudci/php:%s"
OC_UBUNTU = "owncloud/ubuntu:20.04"
PHPDOC_PHPDOC = "phpdoc/phpdoc:3"
PLUGINS_GITHUB_RELEASE = "plugins/github-release"
PLUGINS_GH_PAGES = "plugins/gh-pages:1"
PLUGINS_S3 = "plugins/s3"
MINIO_MC = "minio/mc:RELEASE.2020-12-18T10-53-53Z"
OC_CI_GOLANG = "owncloudci/golang:1.20"
OC_CI_ALPINE = "owncloudci/alpine:latest"
OC_CI_WAIT_FOR = "owncloudci/wait-for:latest"
OC_CI_NODEJS = "owncloudci/nodejs:18"
SONARSOURCE_SONAR_SCANNER_CLI = "sonarsource/sonar-scanner-cli"
POSTGRES_ALPINE = "postgres:alpine3.18"
KEYCLOAK = "quay.io/keycloak/keycloak:22.0.4"

DEFAULT_PHP_VERSION = "8.1"

# minio mc environment variables
MINIO_MC_ENV = {
    "CACHE_BUCKET": {
        "from_secret": "cache_s3_bucket",
    },
    "MC_HOST": {
        "from_secret": "cache_s3_server",
    },
    "AWS_ACCESS_KEY_ID": {
        "from_secret": "cache_s3_access_key",
    },
    "AWS_SECRET_ACCESS_KEY": {
        "from_secret": "cache_s3_secret_key",
    },
}

config = {
    "branches": [
        "main",
    ],
    "codestyle": True,
    "phpstan": True,
    "phan": True,
    "phpunit": True,
}

trigger = {
    "ref": [
        "refs/heads/main",
        "refs/pull/**",
        "refs/tags/**",
    ],
}

def main(ctx):
    return integrationTest()

def integrationTest():
    environment = {
        "OCIS_URL": "https://ocis:9200",
        "OCIS_CONFIG_DIR": "/root/.ocis/config",  # needed for checking config later
        "OCIS_LOG_LEVEL": "error",
        "OCIS_INSECURE": "true",
        "IDM_ADMIN_PASSWORD": "admin",  # override the random admin password from `ocis init`
        "PROXY_AUTOPROVISION_ACCOUNTS": "true",
        "PROXY_ROLE_ASSIGNMENT_DRIVER": "oidc",
        "OCIS_OIDC_ISSUER": "http://${KEYCLOAK_DOMAIN:-keycloak}/realms/${KEYCLOAK_REALM:-oCIS}",
        "PROXY_OIDC_REWRITE_WELLKNOWN": "true",
        "WEB_OIDC_CLIENT_ID": "web",
        "PROXY_USER_OIDC_CLAIM": "preferred_username",
        "PROXY_USER_CS3_CLAIM": "username",
        "OCIS_ADMIN_USER_ID": "",
        "OCIS_EXCLUDE_RUN_SERVICES": "idp",
        "GRAPH_ASSIGN_DEFAULT_USER_ROLE": "false",
        "GRAPH_USERNAME_MATCH": "none",
    }
    ocis_bin = "ocis/ocis/bin/ocis"
    result = {
        "kind": "pipeline",
        "type": "docker",
        "name": "ocis",
        "steps": buildOcis() + keycloakService() +
                 [
                     {
                         "name": "ocis",
                         "image": OC_CI_GOLANG,
                         "detach": True,
                         "environment": environment,
                         "commands": [
                             "%s init --insecure true" % ocis_bin,
                             "%s server" % ocis_bin,
                         ],
                     },
                     {
                         "name": "wait-for-ocis-server",
                         "image": OC_CI_WAIT_FOR,
                         "commands": [
                             "wait-for -it ocis:9200 -t 300",
                         ],
                     },
                     {
                         "name": "integration-tests",
                         "image": OC_CI_PHP % DEFAULT_PHP_VERSION,
                         "commands": [
                             "curl -v -X GET 'https://ocis:9200/.well-known/openid-configuration' -k",
                             "make test-php-integration-ci",
                         ],
                     },
                 ],
        "services": postgresService(),
        "trigger": trigger,
    }
    return result

def buildOcis():
    ocis_repo_url = "https://github.com/owncloud/ocis.git"

    return [
        {
            "name": "clone-ocis",
            "image": OC_CI_GOLANG,
            "commands": [
                "git clone -b master --single-branch %s" % ocis_repo_url,
                "pwd",
            ],
        },
        {
            "name": "generate-ocis",
            "image": OC_CI_NODEJS,
            "commands": [
                # we cannot use the $GOPATH here because of different base image
                "cd ocis",
                "retry -t 3 'make ci-node-generate'",
            ],
        },
        {
            "name": "build-ocis",
            "image": OC_CI_GOLANG,
            "commands": [
                "cd ocis/ocis",
                "retry -t 3 'make build'",
            ],
            "environment": {
                "HTTP_PROXY": {
                    "from_secret": "drone_http_proxy",
                },
                "HTTPS_PROXY": {
                    "from_secret": "drone_http_proxy",
                },
            },
        },
    ]

def postgresService():
    return [
        {
            "name": "postgres",
            "image": POSTGRES_ALPINE,
            "environment": {
                "POSTGRES_DB": "keycloak",
                "POSTGRES_USER": "keycloak",  # needed for checking config later
                "POSTGRES_PASSWORD": "keycloak",
            },
        },
    ]

def keycloakService():
    return [
        {
            "name": "wait-for-postgres",
            "image": OC_CI_WAIT_FOR,
            "commands": [
                "wait-for -it postgres:5432 -t 300",
            ],
        },
        {
            "name": "keycloak",
            "image": KEYCLOAK,
            "detach": True,
            "environment": {
                "OCIS_DOMAIN": "https://ocis:9200",
                "KC_HOSTNAME": "http://keycloak",
                "KC_DB": "postgres",
                "KC_DB_URL": "jdbc:postgresql://postgres:5432/keycloak",
                "KC_DB_USERNAME": "keycloak",
                "KC_DB_PASSWORD": "keycloak",
                "KC_FEATURES": "impersonation",
                "KEYCLOAK_ADMIN": "admin",
                "KEYCLOAK_ADMIN_PASSWORD": "admin",
            },
            "commands": [
                "mkdir -p /opt/keycloak/data/import",
                "cp tests/integration/docker/keycloak/ocis-realm.dist.json /opt/keycloak/data/import/ocis-realm.json",
                "/opt/keycloak/bin/kc.sh start --proxy edge --spi-connections-http-client-default-disable-trust-manager=true --import-realm --health-enabled=true",
            ],
        },
        {
            "name": "wait-for-keycloak",
            "image": OC_CI_WAIT_FOR,
            "commands": [
                "wait-for -it keycloak:8080 -t 300",
            ],
        },
    ]
