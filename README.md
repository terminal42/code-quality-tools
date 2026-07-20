# Code quality tools

Shared, opinionated code-quality configuration for terminal42 projects. Each tool is installed in its own directory, so its dependencies do not conflict with those of the consuming project.

## Installation

Install the Composer plugin as a development dependency:

```bash
composer require --dev terminal42/code-quality-tools
```

Allow the plugin when Composer asks. For non-interactive installations, add it explicitly:

```json
{
    "config": {
        "allow-plugins": {
            "terminal42/code-quality-tools": true
        }
    }
}
```

The plugin installs and updates the isolated dependencies below `vendor/terminal42/code-quality-tools/tools/` whenever Composer runs in development mode.

## PHP tools

Add the tools required by the project to the `scripts` section of its `composer.json`. A typical PHP project uses ECS, Rector, and PHPStan:

```json
{
    "scripts": {
        "cs-fixer": "@php vendor/terminal42/code-quality-tools/tools/ecs/vendor/bin/ecs check src tests --config vendor/terminal42/code-quality-tools/tools/ecs/config.php --fix --ansi",
        "rector": "@php vendor/terminal42/code-quality-tools/tools/rector/vendor/bin/rector process src tests --config vendor/terminal42/code-quality-tools/tools/rector/config.php --ansi",
        "phpstan": "@php vendor/terminal42/code-quality-tools/tools/phpstan/vendor/bin/phpstan analyze src tests --configuration vendor/terminal42/code-quality-tools/tools/phpstan/config.php --ansi",
        "code-quality": [
            "@cs-fixer",
            "@rector",
            "@phpstan"
        ]
    }
}
```

Adjust `src`, `tests`, and the other paths to match the project. Run all configured checks with:

```bash
composer run code-quality
```

The other bundled PHP tools can be added in the same way:

```json
{
    "scripts": {
        "composer-dependencies": "@php vendor/terminal42/code-quality-tools/tools/composer-dependency-analyser/vendor/bin/composer-dependency-analyser --config vendor/terminal42/code-quality-tools/tools/composer-dependency-analyser/config.php",
        "twig-cs-fixer": "@php vendor/terminal42/code-quality-tools/tools/twig-cs-fixer/vendor/bin/twig-cs-fixer fix templates --config vendor/terminal42/code-quality-tools/tools/twig-cs-fixer/config.php",
        "yaml-lint": "@php vendor/terminal42/code-quality-tools/tools/yamllint/vendor/bin/yaml-lint config .github"
    }
}
```

Projects may extend the shared configuration through these local files:

- `ecs.php`
- `rector.php`
- `phpstan.neon`, `phpstan.neon.dist`, `phpstan.dist.neon`, or `phpstan-baseline.neon`
- `composer-dependency-analyser.php`

## JavaScript and CSS tools

Biome, ESLint, and Stylelint are also installed in isolated tool directories. Add only the commands relevant to the project and adjust the target paths or globs as needed:

```json
{
    "scripts": {
        "biome": "vendor/terminal42/code-quality-tools/tools/biome/node_modules/.bin/biome check assets layout --config-path vendor/terminal42/code-quality-tools/tools/biome/biome.json --write",
        "eslint": "vendor/terminal42/code-quality-tools/tools/eslint/node_modules/.bin/eslint assets layout --config vendor/terminal42/code-quality-tools/tools/eslint/eslint.config.js --fix",
        "stylelint": "vendor/terminal42/code-quality-tools/tools/stylelint/node_modules/.bin/stylelint \"assets/**/*.{css,scss}\" \"layout/**/*.{css,scss}\" --config vendor/terminal42/code-quality-tools/tools/stylelint/stylelint.config.js --fix",
        "code-quality": [
            "@biome",
            "@eslint",
            "@stylelint"
        ]
    }
}
```

When combining PHP and Node tools, keep a single `code-quality` array containing every selected script alias.

The shared ESLint configuration additionally reads `.eslintrc.json`, while Stylelint reads `.stylelintrc`, when those files exist in the project root.

Add a root `package.json` when using the Node-based tools so the reusable workflow sets up Node.js and installs the project's own packages as well.

## GitHub Actions

Create `.github/workflows/code-quality.yml` in the consuming project:

```yaml
name: Code Quality

on:
    push: ~
    pull_request: ~

permissions: read-all

jobs:
    code-quality:
        uses: 'terminal42/code-quality-tools/.github/workflows/code-quality.yml@main'
```

The reusable workflow installs the project dependencies and runs `composer run code-quality`. Every script referenced by `code-quality` must therefore be available in the project.
