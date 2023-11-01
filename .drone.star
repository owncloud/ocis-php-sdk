OC_CI_PHP = "owncloudci/php:%s"
OC_UBUNTU = "owncloud/ubuntu:20.04"
PHPDOC_PHPDOC = "phpdoc/phpdoc:3"
PLUGINS_GITHUB_RELEASE = "plugins/github-release"
PLUGINS_GH_PAGES = "plugins/gh-pages:1"
PLUGINS_S3 = "plugins/s3"
MINIO_MC = "minio/mc:RELEASE.2020-12-18T10-53-53Z"
OC_CI_ALPINE = "owncloudci/alpine:latest"
SONARSOURCE_SONAR_SCANNER_CLI = "sonarsource/sonar-scanner-cli"
OC_CI_GOLANG = "owncloudci/golang:1.20"
OC_CI_WAIT_FOR = "owncloudci/wait-for:latest"
OC_CI_NODEJS = "owncloudci/nodejs:18"
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
    return (
        tests(
            ctx,
            [
                ["codestyle", "make test-php-style"],
                ["phpstan", "make test-php-phpstan"],
                ["phan", "make test-php-phan"],
            ],
        ) +
        phpunit(ctx, [DEFAULT_PHP_VERSION], True) +
        phpunit(ctx, [8.2], False) +
        phpIntegrationTest() +
        sonarAnalysis(ctx) +
        docs()
    )

def phpIntegrationTest():
    return [{
        "kind": "pipeline",
        "type": "docker",
        "name": "integration-test",
        "steps": buildOcis() + keycloakService() + ocisService() +
                 [
                     {
                         "name": "integration-tests",
                         "image": OC_CI_PHP % DEFAULT_PHP_VERSION,
                         "environment": {
                             "OCIS_URL": "https://ocis:9200",
                         },
                         "commands": [
                             "make test-php-integration-ci",
                         ],
                     },
                 ],
        "services": postgresService(),
        "trigger": trigger,
    }]

def ocisService():
    ocis_bin = "ocis/ocis/bin/ocis"
    environment = {
        "OCIS_URL": "https://ocis:9200",
        "OCIS_LOG_LEVEL": "error",
        "IDM_ADMIN_PASSWORD": "admin",  # override the random admin password from `ocis init`
        "PROXY_AUTOPROVISION_ACCOUNTS": "true",
        "PROXY_ROLE_ASSIGNMENT_DRIVER": "oidc",
        "OCIS_OIDC_ISSUER": "http://keycloak:8080/realms/oCIS",
        "PROXY_OIDC_REWRITE_WELLKNOWN": "true",
        "WEB_OIDC_CLIENT_ID": "web",
        "PROXY_USER_OIDC_CLAIM": "preferred_username",
        "PROXY_USER_CS3_CLAIM": "username",
        "OCIS_ADMIN_USER_ID": "",
        "OCIS_EXCLUDE_RUN_SERVICES": "idp",
        "GRAPH_ASSIGN_DEFAULT_USER_ROLE": "false",
        "GRAPH_USERNAME_MATCH": "none",
    }

    return [
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
    ]

def buildOcis():
    ocis_repo_url = "https://github.com/owncloud/ocis.git"
    return [
        {
            "name": "clone-ocis",
            "image": OC_CI_GOLANG,
            "commands": [
                "source .drone.env",
                "git clone -b $OCIS_BRANCH --single-branch %s" % ocis_repo_url,
                "cd ocis",
                "git checkout $OCIS_COMMITID",
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
                "OCIS_DOMAIN": "ocis:9200",
                "KC_HOSTNAME": "keycloak:8080",
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
                "/opt/keycloak/bin/kc.sh start-dev --proxy edge --spi-connections-http-client-default-disable-trust-manager=true --import-realm --health-enabled=true",
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

def tests(ctx, tests):
    pipelines = []
    for test in tests:
        if test[0] in config and config[test[0]]:
            pipelines += [
                {
                    "kind": "pipeline",
                    "name": test[0],
                    "steps": [
                        {
                            "name": test[0],
                            "image": OC_CI_PHP % DEFAULT_PHP_VERSION,
                            "commands": [
                                "composer install",
                                test[1],
                            ],
                        },
                    ],
                    "trigger": trigger,
                },
            ]
    return pipelines

def phpunit(ctx, phpversions, coverage):
    pipelines = []
    if "phpunit" in config and config["phpunit"]:
        for php in phpversions:
            name = "php-unit-test-%s" % php
            steps = [
                {
                    "name": "php-unit-test",
                    "image": OC_CI_PHP % php,
                    "commands": [
                        "composer install",
                        "make test-php-unit",
                    ],
                },
            ]
            if coverage:
                steps.append({
                    "name": "coverage-rename",
                    "image": OC_CI_PHP % php,
                    "commands": [
                        "mv tests/output/clover.xml tests/output/clover-%s.xml" % name,
                    ],
                })
                steps.append({
                    "name": "coverage-cache-1",
                    "image": PLUGINS_S3,
                    "settings": {
                        "endpoint": {
                            "from_secret": "cache_s3_server",
                        },
                        "bucket": "cache",
                        "source": "tests/output/clover-%s.xml" % name,
                        "target": "%s/%s" % (ctx.repo.slug, ctx.build.commit + "-${DRONE_BUILD_NUMBER}"),
                        "path_style": True,
                        "strip_prefix": "tests/output",
                        "access_key": {
                            "from_secret": "cache_s3_access_key",
                        },
                        "secret_key": {
                            "from_secret": "cache_s3_secret_key",
                        },
                    },
                })
            pipelines += [
                {
                    "kind": "pipeline",
                    "name": name,
                    "steps": steps,
                    "trigger": trigger,
                },
            ]
    return pipelines

def docs():
    return [{
        "kind": "pipeline",
        "type": "docker",
        "name": "docs",
        "platform": {
            "os": "linux",
            "arch": "amd64",
        },
        "steps": [
            {
                "name": "dependencies",
                "image": OC_CI_PHP % DEFAULT_PHP_VERSION,
                "commands": [
                    "composer install",
                ],
            },
            {
                "name": "docs-generate",
                "image": PHPDOC_PHPDOC,
            },
            {
                "name": "publish",
                "image": PLUGINS_GH_PAGES,
                "settings": {
                    "username": {
                        "from_secret": "github_username",
                    },
                    "password": {
                        "from_secret": "github_token",
                    },
                    "pages_directory": "docs",
                    "copy_contents": "true",
                    "target_branch": "docs",
                    "delete": "true",
                },
                "when": {
                    "ref": {
                        "exclude": [
                            "refs/pull/**",
                        ],
                    },
                },
            },
        ],
        "trigger": trigger,
    }]

def sonarAnalysis(ctx, phpVersion = DEFAULT_PHP_VERSION):
    sonar_env = {
        "SONAR_TOKEN": {
            "from_secret": "sonar_token",
        },
        "SONAR_SCANNER_OPTS": "-Xdebug",
    }

    if ctx.build.event == "pull_request":
        sonar_env.update({
            "SONAR_PULL_REQUEST_BASE": "%s" % (ctx.build.target),
            "SONAR_PULL_REQUEST_BRANCH": "%s" % (ctx.build.source),
            "SONAR_PULL_REQUEST_KEY": "%s" % (ctx.build.ref.replace("refs/pull/", "").split("/")[0]),
        })

    repo_slug = ctx.build.source_repo if ctx.build.source_repo else ctx.repo.slug

    result = [{
        "kind": "pipeline",
        "type": "docker",
        "name": "sonar-analysis",
        "clone": {
            "disable": True,  # Sonarcloud does not apply issues on already merged branch
        },
        "steps": [
                     {
                         "name": "clone",
                         "image": OC_CI_ALPINE,
                         "commands": [
                             "git clone https://github.com/%s.git ." % repo_slug,
                             "git checkout $DRONE_COMMIT",
                         ],
                     },
                 ] +
                 [
                     {
                         "name": "sync-from-cache",
                         "image": MINIO_MC,
                         "environment": MINIO_MC_ENV,
                         "commands": [
                             "mkdir -p results",
                             "mc alias set cache $MC_HOST $AWS_ACCESS_KEY_ID $AWS_SECRET_ACCESS_KEY",
                             "mc mirror cache/cache/%s/%s results/" % (ctx.repo.slug, ctx.build.commit + "-${DRONE_BUILD_NUMBER}"),
                         ],
                     },
                     {
                         "name": "list-coverage-results",
                         "image": OC_CI_PHP % phpVersion,
                         "commands": [
                             "ls -l results",
                         ],
                     },
                     {
                         "name": "sonarcloud",
                         "image": SONARSOURCE_SONAR_SCANNER_CLI,
                         "environment": sonar_env,
                         "when": {
                             "instance": [
                                 "drone.owncloud.services",
                                 "drone.owncloud.com",
                             ],
                         },
                     },
                     {
                         "name": "purge-cache",
                         "image": MINIO_MC,
                         "environment": MINIO_MC_ENV,
                         "commands": [
                             "mc alias set cache $MC_HOST $AWS_ACCESS_KEY_ID $AWS_SECRET_ACCESS_KEY",
                             "mc rm --recursive --force cache/cache/%s/%s" % (ctx.repo.slug, ctx.build.commit + "-${DRONE_BUILD_NUMBER}"),
                         ],
                     },
                 ],
        "depends_on": [
            "php-unit-test-%s" % DEFAULT_PHP_VERSION,
        ],
        "trigger": trigger,
    }]
    return result
