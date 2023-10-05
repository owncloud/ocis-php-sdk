OC_CI_PHP = "owncloudci/php:%s"
OC_UBUNTU = "owncloud/ubuntu:20.04"
PLUGINS_GITHUB_RELEASE = "plugins/github-release"

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
    ) + phpunit(ctx, [8.1, 8.2])


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
