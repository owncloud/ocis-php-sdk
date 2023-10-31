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

DEFAULT_PHP_VERSION = "8.1"

dir = {
    "base": "/var/www/owncloud",
    "federated": "/var/www/owncloud/federated",
    "server": "/var/www/owncloud/server",
    "web": "/var/www/owncloud/web",
    "ocis": "/var/www/owncloud/ocis-build",
    "commentsFile": "/var/www/owncloud/web/comments.file",
    "app": "/srv/app",
    "config": "/srv/config",
    "ocisConfig": "/srv/config/drone/config-ocis.json",
    "ocisIdentifierRegistrationConfig": "/srv/config/drone/identifier-registration.yml",
    "ocisRevaDataRoot": "/srv/app/tmp/ocis/owncloud/data/",
    "testingDataDir": "/srv/app/testing/data/",
}

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
        "IDM_ADMIN_PASSWORD": "admin",  # override the random admin password from `ocis init`
        "PROXY_AUTOPROVISION_ACCOUNTS": True,
        "PROXY_ROLE_ASSIGNMENT_DRIVER": "oidc",
        "OCIS_OIDC_ISSUER": "https://${KEYCLOAK_DOMAIN:-keycloak.owncloud.test}/realms/${KEYCLOAK_REALM:-oCIS}",
        "PROXY_OIDC_REWRITE_WELLKNOWN": True,
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
        "steps": buildOcis() +
                 [
                     {
                         "name": "ocis",
                         "image": OC_CI_GOLANG,
                         "detach": True,
                         "environment": environment,
                         "commands": [
                             "%s init --insecure true" % ocis_bin,
                             "%s server" % ocis_bin
                         ],
                     },
                     {
                         "name": "wait-for-ocis-server",
                         "image": OC_CI_WAIT_FOR,
                         "commands": [
                             "wait-for -it ocis:9200 -t 300",
                         ],
                     },
                 ],
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
        },
    ]
