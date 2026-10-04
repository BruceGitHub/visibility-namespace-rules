# AGENTS.md

## Toolchain
- No PHP/Composer on the host. Run every command through the Docker `app` service.
- First run: `docker compose up -d --build`, then `docker compose exec app composer install`.
- `composer.lock` is committed; `vendor/` is gitignored. Files created by the container are root-owned — delete them with a root container (`docker run --rm -v "$PWD":/app -w /app private-namespace-rules-app rm -rf vendor`), not plain `rm`.

## Commands (inside the container)
- Tests: `docker compose exec app vendor/bin/pest` (config `phpunit.xml`, tests under `tests/`).
- Static analysis: `docker compose exec app vendor/bin/phpstan analyse` (level `max`, analyses `src` only).
- Format: `docker compose exec app vendor/bin/php-cs-fixer fix --allow-risky=yes`. `--allow-risky=yes` is mandatory: `.php-cs-fixer.dist.php` enables the risky `strict_param` fixer.
- To verify a config against a file: `vendor/bin/phpstan analyse <path> -c <config.neon>`.
- Distribution image: `docker build -f docker/Dockerfile -t visibility-namespace-rules:dist .` then `docker run --rm -v "$PWD":/app -w /app visibility-namespace-rules:dist src`.

## Layout
- PSR-4: `BruceGitHub\VisibilityNamespaceRules\` → `src/`, `Tests\` → `tests/`.
- `src/NamespaceVisibilityResolver.php` — pure visibility logic (namespace matching, exposed/friend resolution). No PHPStan/AST dependency; add logic here when possible.
- `src/NamespaceRule.php` — value object for one configured namespace.
- `src/PrivateNamespaceRule.php` — AST rule: extracts class-like references and delegates to the resolver. Only "leaf" reference nodes are handled; nullable/union/intersection types are traversed recursively, so each reference is reported exactly once.
- `extension.neon` — `parametersSchema` + default `%namespaceVisibility%` + service registration. `phpstan.neon` merely includes it.
- `docker/Dockerfile` — standalone distribution image (bundled PHPStan), built **only** from `composer.json`, `composer.lock`, `src/` and `extension.neon`. `docker/entrypoint.sh` composes a temp config from `extension.neon` + the consumer config and forwards args. Keep the `COPY` list in sync when adding files.
- `.github/workflows/ci.yml` — tests/phpstan/php-cs-fixer on PHP 8.2–8.4. There is no registry publishing: consumers build `docker/Dockerfile` locally.
- `tests/NamespaceVisibilityResolverTest.php` — Pest unit tests for the resolver.
- `tests/PrivateNamespaceRuleTest.php` / `PrivateByDefaultRuleTest.php` — `PHPStan\Testing\RuleTestCase` integration tests.
- `tests/data/**` — fixtures. Files here must NOT match `*Test.php` or PHPUnit will try to run them.

## Configuration contract
- Consumer parameter: `namespaceVisibility` (schema in `extension.neon`). The service binds it via the constructor argument named `namespaceVisibility`; renaming one without the other silently disables configuration.
- Modes: `default: public` (unlisted = public, listed = private) and `default: private` (unlisted = private to its own tree, listed = public). A listed entry's `visibility` defaults to the opposite of `default`.
- `exposed` accepts FQCN or namespace prefixes; `allowed_namespaces` is the C# `InternalsVisibleTo` equivalent.
- Error identifier is `privateNamespace.access`; message format is asserted in the integration tests.

## Gotchas
- PHPStan **2.x is required**: `php-cs-fixer` pulls `nikic/php-parser` v5, which fatals PHPStan 1.x (`PhpParserDecorator` abstract `Parser::getTokens`). The `^1.11 || ^2.0` constraint exists for API compatibility, but a fresh install resolves 2.x.
- `rector` was removed: it was broken (phpdoc-parser mismatch) and is not part of the toolchain.
- Custom PHPStan parameters must be declared in `parametersSchema`, using Nette syntax `?key: type()` inside `structure({...})`; otherwise PHPStan aborts with "Unexpected item 'parameters › namespaceVisibility'".
