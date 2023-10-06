OC_CI_PHP = "owncloudci/php:%s"
OC_UBUNTU = "owncloud/ubuntu:20.04"
PHPDOC_PHPDOC = "phpdoc/phpdoc:3"
PLUGINS_GITHUB_RELEASE = "plugins/github-release"
PLUGINS_GH_PAGES = "plugins/gh-pages:1"

DEFAULT_PHP_VERSION = "8.1"

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
        "refs/head/main",
        "refs/pull/**",
        "refs/tags/**",
    ],
}


def main(ctx):
    return tests(
        ctx,
        [
            ["codestyle", "make test-php-style"],
            ["phpstan", "make test-php-phpstan"],
            ["phan", "make test-php-phan"],
        ],
    ) + phpunit(ctx, [8.1, 8.2]) + docs()


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


def phpunit(ctx, phpVersions):
    pipelines = []
    if "phpunit" in config and config["phpunit"]:
        for php in phpVersions:
            pipelines += [
                {
                    "kind": "pipeline",
                    "name": "php-unit-test-%s" % php,
                    "steps": [
                        {
                            "name": "php-unit-test",
                            "image": OC_CI_PHP % php,
                            "commands": [
                                "composer install",
                                "make test-php-unit",
                            ],
                        },
                    ],
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
        "trigger": {
            "ref": [
                "refs/heads/master",
                "refs/pull/**",
            ],
        },
    }]
