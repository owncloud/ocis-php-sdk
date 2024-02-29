KEYCLOAK = "quay.io/keycloak/keycloak:22.0.4"
MINIO_MC = "minio/mc:RELEASE.2020-12-18T10-53-53Z"
OC_CI_ALPINE = "owncloudci/alpine:latest"
OC_CI_DRONE_SKIP_PIPELINE = "owncloudci/drone-skip-pipeline"
OC_CI_GOLANG = "owncloudci/golang:1.21"
OC_CI_NODEJS = "owncloudci/nodejs:18"
OC_CI_PHP = "owncloudci/php:%s"
OC_CI_WAIT_FOR = "owncloudci/wait-for:latest"
OC_UBUNTU = "owncloud/ubuntu:20.04"
PHPDOC_PHPDOC = "phpdoc/phpdoc:3"
PLUGINS_GH_PAGES = "plugins/gh-pages:1"
PLUGINS_GITHUB_RELEASE = "plugins/github-release"
PLUGINS_S3 = "plugins/s3"
PLUGINS_S3_CACHE = "plugins/s3-cache:1"
POSTGRES_ALPINE = "postgres:alpine3.18"
SONARSOURCE_SONAR_SCANNER_CLI = "sonarsource/sonar-scanner-cli"
OC_CI_BUILDIFIER = "owncloudci/bazel-buildifier:latest"

DEFAULT_PHP_VERSION = "8.1"
dir = {
    "base": "/drone/src",
    "ocis_bin": "/drone/src/ocis_bin",
    "ociswrapper_bin": "/drone/src/ociswrapper_bin",
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
    "php-unit": True,
    "ocisBranches": ["master", "stable"],
}

trigger = {
    "ref": [
        "refs/heads/main",
        "refs/pull/**",
        "refs/tags/**",
    ],
}

def main(ctx):
    codeStylePipeline = tests(ctx, "codestyle", "make test-php-style", [DEFAULT_PHP_VERSION], False)
    phpStanPipeline = tests(ctx, "phpstan", "make test-php-phpstan", [DEFAULT_PHP_VERSION], False)
    phanPipeline = tests(ctx, "phan", "make test-php-phan", [DEFAULT_PHP_VERSION], False)
    testsPipelinesWithCoverage = tests(ctx, "php-unit", "make test-php-unit", [DEFAULT_PHP_VERSION], True, True)
    testsPipelinesWithCoverage += phpIntegrationTest(ctx, [DEFAULT_PHP_VERSION], True)
    testsPipelinesWithoutCoverage = tests(ctx, "php-unit", "make test-php-unit", [8.2, 8.3], False, True)
    testsPipelinesWithoutCoverage += phpIntegrationTest(ctx, [8.2, 8.3], False)
    sonarPipeline = sonarAnalysis(ctx)
    dependsOn(testsPipelinesWithCoverage, sonarPipeline)
    afterPipelines = codeStylePipeline + phpStanPipeline + phanPipeline + testsPipelinesWithCoverage + testsPipelinesWithoutCoverage
    dependsOn(cacheDependencies(), afterPipelines)
    docsPipeline = docs()
    dependsOn(cacheDependencies(), docsPipeline)
    return (
        checkStarlark() +
        cacheDependencies() +
        cacheOcisPipeline(ctx) +
        codeStylePipeline +
        phpStanPipeline +
        phanPipeline +
        testsPipelinesWithCoverage +
        testsPipelinesWithoutCoverage +
        sonarPipeline +
        docsPipeline
    )

def checkStarlark():
    return [{
        "kind": "pipeline",
        "type": "docker",
        "name": "check-starlark",
        "steps": [
            {
                "name": "format-check-starlark",
                "image": OC_CI_BUILDIFIER,
                "commands": [
                    "buildifier --mode=check .drone.star",
                ],
            },
            {
                "name": "show-diff",
                "image": OC_CI_BUILDIFIER,
                "commands": [
                    "buildifier --mode=fix .drone.star",
                    "git diff",
                ],
                "when": {
                    "status": [
                        "failure",
                    ],
                },
            },
        ],
        "depends_on": [],
        "trigger": {
            "ref": [
                "refs/pull/**",
            ],
        },
    }]

def getCommitId(branch):
    if branch == "master":
        return "$OCIS_COMMITID"
    return "$OCIS_STABLE_COMMITID"

def getBranchName(branch):
    if branch == "master":
        return "$OCIS_BRANCH"
    return "$OCIS_STABLE_BRANCH"

def phpIntegrationTest(ctx, phpVersions, coverage):
    pipelines = []
    for phpVersion in phpVersions:
        for index, branch in enumerate(config["ocisBranches"]):
            steps = keycloakService() + getOcislatestCommitId(ctx, branch) + restoreOcisCache(ctx, branch) + ocisService() + cacheRestore()
            name = "php-integration-%s-%s" % (phpVersion, branch)
            steps.append(
                {
                    "name": "php-integration",
                    "image": OC_CI_PHP % phpVersion,
                    "environment": {
                        "OCIS_URL": "https://ocis:9200",
                        "OCISWRAPPER_URL": "http://ocis:5200",
                        "COMPOSER_HOME": "%s/.cache/composer" % dir["base"],
                    },
                    "commands": [
                        installPhpXdebugCommand(phpVersion),
                        "make test-php-integration-ci",
                    ],
                },
            )
            if coverage and index == 0:
                coverageName = "php-integration-%s" % phpVersion
                steps += coverageSteps(ctx, coverageName)
            pipelines += [
                {
                    "kind": "pipeline",
                    "name": name,
                    "steps": steps,
                    "services": postgresService(),
                    "depends_on": ["cache-ocis-" + branch],
                    "trigger": trigger,
                },
            ]

    return pipelines

def ocisService():
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
                "%s/ocis init --insecure true" % dir["ocis_bin"],
                "%s/ociswrapper serve --bin %s/ocis --url %s" % (dir["ociswrapper_bin"], dir["ocis_bin"], "https://ocis:9200"),
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

def buildOcis(branch):
    ocis_commit_id = getCommitId(branch)
    ocis_branch = getBranchName(branch)
    ocis_repo_url = "https://github.com/owncloud/ocis.git"
    return [
        {
            "name": "clone-ocis-%s" % branch,
            "image": OC_CI_GOLANG,
            "commands": [
                "source .drone.env",
                "git clone -b %s --single-branch %s" % (ocis_branch, ocis_repo_url),
                "cd ocis",
                "git checkout %s" % ocis_commit_id,
            ],
        },
        {
            "name": "generate-ocis-%s" % branch,
            "image": OC_CI_NODEJS,
            "commands": [
                # we cannot use the $GOPATH here because of different base image
                "cd ocis",
                "retry -t 3 'make ci-node-generate'",
            ],
        },
        {
            "name": "build-ocis-%s" % branch,
            "image": OC_CI_GOLANG,
            "commands": [
                ". ./.drone.env",
                "cd ocis/ocis",
                "retry -t 3 'make build'",
                "mkdir -p %s/%s" % (dir["base"], ocis_commit_id),
                "cp bin/ocis %s/%s" % (dir["base"], ocis_commit_id),
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
        {
            "name": "build-ociswrapper",
            "image": OC_CI_GOLANG,
            "commands": [
                ". ./.drone.env",
                "make -C ocis/tests/ociswrapper build",
                "mkdir -p %s/%s" % (dir["base"], ocis_commit_id),
                "cp ocis/tests/ociswrapper/bin/ociswrapper %s/%s/" % (dir["base"], ocis_commit_id),
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

def cacheOcisPipeline(ctx):
    pipelines = []
    for branch in config["ocisBranches"]:
        steps = getOcislatestCommitId(ctx, branch) + checkForExistingOcisCache(ctx, branch) + buildOcis(branch) + cacheOcis(branch)
        pipelines += [{
            "kind": "pipeline",
            "type": "docker",
            "name": "cache-ocis-%s" % branch,
            "clone": {
                "disable": True,
            },
            "steps": steps,
            "volumes": [{
                "name": "gopath",
                "temp": {},
            }],
            "trigger": trigger,
        }]
    return pipelines

def getOcislatestCommitId(ctx, branch):
    repo_path = "https://raw.githubusercontent.com/owncloud/ocis-php-sdk/%s" % ctx.build.commit
    return [
        {
            "name": "get-ocis-%s-latest-commit-id" % branch,
            "image": OC_CI_ALPINE,
            "commands": [
                "curl -o .drone.env %s/.drone.env" % repo_path,
                "curl -o check-oCIS-cache.sh %s/tests/check-oCIS-cache.sh" % repo_path,
                "curl -o get-latest-ocis-commit-id.sh %s/tests/get-latest-ocis-commit-id.sh" % repo_path,
                ". ./.drone.env",
                "bash get-latest-ocis-commit-id.sh %s" % getBranchName(branch),
            ],
            "when": {
                "event": [
                    "cron",
                ],
            },
        },
    ]

def checkForExistingOcisCache(ctx, branch):
    repo_path = "https://raw.githubusercontent.com/owncloud/ocis-php-sdk/%s" % ctx.build.commit  #% ctx.build.commit
    commands = [
        ". ./.drone.env",
        "mc alias set s3 $MC_HOST $AWS_ACCESS_KEY_ID $AWS_SECRET_ACCESS_KEY",
        "mc ls --recursive s3/$CACHE_BUCKET/ocis-build",
        "bash check-oCIS-cache.sh %s" % getCommitId(branch),
    ]

    if ctx.build.event != "cron":
        commands = [
            "curl -o .drone.env %s/.drone.env" % repo_path,
            "curl -o check-oCIS-cache.sh %s/tests/check-oCIS-cache.sh" % repo_path,
        ] + commands

    return [
        {
            "name": "check-for-existing-cache",
            "image": MINIO_MC,
            "environment": MINIO_MC_ENV,
            "commands": commands,
        },
    ]

def cacheOcis(branch):
    ocis_commit_id = getCommitId(branch)
    return [{
        "name": "upload-ocis-cache-%s" % branch,
        "image": MINIO_MC,
        "environment": MINIO_MC_ENV,
        "commands": [
            ". ./.drone.env",
            "mc alias set s3 $MC_HOST $AWS_ACCESS_KEY_ID $AWS_SECRET_ACCESS_KEY",
            "mc cp -r -a %s/%s/ocis s3/$CACHE_BUCKET/ocis-build/%s" % (dir["base"], ocis_commit_id, ocis_commit_id),
            "mc cp -r -a %s/%s/ociswrapper s3/$CACHE_BUCKET/ocis-build/%s" % (dir["base"], ocis_commit_id, ocis_commit_id),
            "mc ls --recursive s3/$CACHE_BUCKET/ocis-build",
        ],
    }]

def restoreOcisCache(ctx, branch):
    repo_path = "https://raw.githubusercontent.com/owncloud/ocis-php-sdk/%s" % ctx.build.commit
    ocis_commit_id = getCommitId(branch)

    return [
        {
            "name": "restore-ocis-cache",
            "image": MINIO_MC,
            "environment": MINIO_MC_ENV,
            "commands": [
                "mkdir -p %s" % dir["ocis_bin"],
                "mkdir -p %s" % dir["ociswrapper_bin"],
                ". ./.drone.env",
                "mc alias set s3 $MC_HOST $AWS_ACCESS_KEY_ID $AWS_SECRET_ACCESS_KEY",
                "mc cp -r -a s3/$CACHE_BUCKET/ocis-build/%s/ocis %s" % (ocis_commit_id, dir["ocis_bin"]),
                "mc cp -r -a s3/$CACHE_BUCKET/ocis-build/%s/ociswrapper %s" % (ocis_commit_id, dir["ociswrapper_bin"]),
            ],
        },
    ]

def postgresService():
    return [
        {
            "name": "postgres",
            "image": POSTGRES_ALPINE,
            "environment": {
                "POSTGRES_DB": "keycloak",
                "POSTGRES_USER": "keycloak",
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
                "cp tests/integration/docker/keycloak/ocis-ci-realm.dist.json /opt/keycloak/data/import/ocis-realm.json",
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

def tests(ctx, name, command, phpVersions, coverage, xdebugNeeded = False):
    pipelines = []
    if name in config and config[name]:
        for phpVersion in phpVersions:
            fullname = "%s-%s" % (name, phpVersion)
            steps = cacheRestore() + [
                {
                    "name": fullname,
                    "image": OC_CI_PHP % phpVersion,
                    "environment": {
                        "COMPOSER_HOME": "%s/.cache/composer" % dir["base"],
                    },
                    "commands": ([installPhpXdebugCommand(phpVersion)] if xdebugNeeded else []) + [
                        "composer install",
                        command,
                    ],
                },
            ]
            if coverage:
                steps += coverageSteps(ctx, fullname)
            pipelines += [
                {
                    "kind": "pipeline",
                    "name": fullname,
                    "steps": steps,
                    "depends_on": [],
                    "trigger": trigger,
                },
            ]
    return pipelines

def coverageSteps(ctx, name):
    return [{
        "name": "coverage-rename",
        "image": OC_CI_PHP % DEFAULT_PHP_VERSION,
        "commands": [
            "mv tests/output/clover.xml tests/output/clover-%s.xml" % name,
        ],
    }, {
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
    }]

def docs():
    return [{
        "kind": "pipeline",
        "type": "docker",
        "name": "docs",
        "platform": {
            "os": "linux",
            "arch": "amd64",
        },
        "steps": cacheRestore() + [
            {
                "name": "dependencies",
                "image": OC_CI_PHP % DEFAULT_PHP_VERSION,
                "environment": {
                    "COMPOSER_HOME": "%s/.cache/composer" % dir["base"],
                },
                "commands": [
                    "composer install",
                ],
            },
            {
                "name": "docs-generate",
                "image": PHPDOC_PHPDOC,
            },
            {
                "name": "publish-api-docs",
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
            {
                "name": "compile-docs-hugo",
                "image": OC_CI_PHP % DEFAULT_PHP_VERSION,
                "commands": [
                    "mkdir docs-hugo",
                    "cat docs-hugo-header.md README.md > docs-hugo/_index.md",
                ],
            },
            {
                "name": "publish-docs-hugo",
                "image": PLUGINS_GH_PAGES,
                "settings": {
                    "username": {
                        "from_secret": "github_username",
                    },
                    "password": {
                        "from_secret": "github_token",
                    },
                    "pages_directory": "docs-hugo",
                    "copy_contents": "true",
                    "target_branch": "docs-hugo",
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
        "depends_on": [],
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
        "depends_on": [],
        "trigger": trigger,
    }]
    return result

def dependsOn(earlierStages, nextStages):
    for earlierStage in earlierStages:
        for nextStage in nextStages:
            nextStage["depends_on"].append(earlierStage["name"])

def cacheDependencies():
    return [
        {
            "kind": "pipeline",
            "type": "docker",
            "name": "cache-dependencies",
            "steps": cacheClearOnEventPush() +
                     composerInstall() +
                     cacheRebuildOnEventPush() +
                     cacheFlushOnEventPush(),
            "depends_on": [],
            "trigger": trigger,
        },
    ]

def composerInstall():
    return [{
        "name": "composer-install",
        "image": OC_CI_PHP % DEFAULT_PHP_VERSION,
        "environment": {
            "COMPOSER_HOME": "%s/.cache/composer" % dir["base"],
        },
        "commands": [
            "composer install",
        ],
    }]

def cacheFlushOnEventPush():
    return [{
        "name": "cache-flush",
        "image": PLUGINS_S3_CACHE,
        "settings": {
            "access_key": {
                "from_secret": "cache_s3_access_key",
            },
            "endpoint": {
                "from_secret": "cache_s3_server",
            },
            "flush": True,
            "flush_age": "14",
            "secret_key": {
                "from_secret": "cache_s3_secret_key",
            },
        },
        "when": {
            "instance": [
                "drone.owncloud.services",
                "drone.owncloud.com",
            ],
        },
    }]

def cacheRestore():
    return [{
        "name": "cache-restore",
        "image": PLUGINS_S3_CACHE,
        "settings": {
            "access_key": {
                "from_secret": "cache_s3_access_key",
            },
            "endpoint": {
                "from_secret": "cache_s3_server",
            },
            "restore": True,
            "secret_key": {
                "from_secret": "cache_s3_secret_key",
            },
        },
        "when": {
            "instance": [
                "drone.owncloud.services",
                "drone.owncloud.com",
            ],
        },
    }]

def cacheClearOnEventPush():
    return [{
        "name": "cache-clear",
        "image": OC_CI_PHP % DEFAULT_PHP_VERSION,
        "commands": [
            "rm -Rf %s/.cache/composer" % dir["base"],
        ],
        "when": {
            "instance": [
                "drone.owncloud.services",
                "drone.owncloud.com",
            ],
        },
    }]

def cacheRebuildOnEventPush():
    return [{
        "name": "cache-rebuild",
        "image": PLUGINS_S3_CACHE,
        "settings": {
            "access_key": {
                "from_secret": "cache_s3_access_key",
            },
            "endpoint": {
                "from_secret": "cache_s3_server",
            },
            "mount": [
                ".cache",
                "composer.lock",
            ],
            "rebuild": True,
            "secret_key": {
                "from_secret": "cache_s3_secret_key",
            },
        },
        "when": {
            "instance": [
                "drone.owncloud.services",
                "drone.owncloud.com",
            ],
        },
    }]

def installPhpXdebugCommand(phpVersion):
    return "add-apt-repository ppa:ondrej/php && apt-get install -y php%s-xdebug" % phpVersion
