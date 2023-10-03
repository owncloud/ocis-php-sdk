OC_CI_PHP = "owncloudci/php:%s"
OC_UBUNTU = "owncloud/ubuntu:20.04"
PLUGINS_GITHUB_RELEASE = "plugins/github-release"

DEFAULT_PHP_VERSION = "8.1"

dir = {
    "base": "/var/www/owncloud",
}

config = {
    "branches": [
        "main",
    ],
    "codestyle": True,
    "validateDailyTarball": True,
    "phpstan": True,
    "phan": {
        "multipleVersions": {
            "phpVersions": [
                DEFAULT_PHP_VERSION,
                "8.1",
            ],
        },
    },
    "phpunit": True,
}


def main(ctx):
    return codestyle(ctx)


def codestyle(ctx):
    if "codestyle" not in config:
        return []
    return [
        {
            "kind": "pipeline",
            "name": "coding-standard",
            "steps": [
                {
                    "name": "coding-standard",
                    "image": OC_CI_PHP % phpVersion,
                    "commands": [
                        "composer install",
                        "make test-php-style",
                    ],
                    "trigger": {
                        "ref": [
                            "refs/pull/**",
                            "refs/tags/**",
                        ],
                    },
                },
            ],
        }
    ]
