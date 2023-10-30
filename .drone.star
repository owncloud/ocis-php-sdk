OC_CI_PHP = "owncloudci/php:%s"
OC_UBUNTU = "owncloud/ubuntu:20.04"
PHPDOC_PHPDOC = "phpdoc/phpdoc:3"
PLUGINS_GITHUB_RELEASE = "plugins/github-release"
PLUGINS_GH_PAGES = "plugins/gh-pages:1"
PLUGINS_S3 = "plugins/s3"
MINIO_MC = "minio/mc:RELEASE.2020-12-18T10-53-53Z"
OC_CI_ALPINE = "owncloudci/alpine:latest"
SONARSOURCE_SONAR_SCANNER_CLI = "sonarsource/sonar-scanner-cli"

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
    return {
        'kind': 'pipeline',
        'name': 'default',
        'steps': [
            {
                'name': 'docker-compose-up',
                'image': 'docker/compose:latest',
                'environment': {
                    'DOCKER_HOST': 'tcp://docker:2375',  # Point to the DinD service
                },
                'commands': [
                    'make run-ocis-with-keycloak',
                ],
                'depends_on': [
                    'dind-service'
                ]
            },
        ],
        'services': [
            {
                'name': 'dind-service',
                'image': 'docker:dind',
                'privileged': True,  # Necessary for DinD
                'volumes': [
                    {
                        'name': 'docker_tmp',
                        'path': '/var/lib/docker',
                    }
                ]
            }
        ],
        'volumes': [
            {
                'name': 'docker_tmp',
                'temp': {},
            }
        ]
    }

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
                }
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
                }
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
