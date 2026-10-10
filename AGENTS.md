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

## Quality gate (authoritative)

The full quality gate is the `quality-kit/` Makefile. `quality-kit/` is **read-only** (treat it like `vendor/`): never edit it. The generated configs live in `quality-kit-generated/` (committed; its `reports/` is gitignored).

```bash
make -C quality-kit quality PROJECT=.. CONFIG=../quality-kit-generated DIRS="src tests" TEST="vendor/bin/pest"
```

This runs, in order: phpstan, style (php-cs-fixer PSR-12), complexity + complexity-test (Mago), phpmd, test (Pest), eslint, format-check (Prettier), duplication (jscpd, 0 clones), security (Semgrep), metrics-gate (phpmetrics). It must be fully green; fix the **code**, not the rules or baselines. A single gate: `make -C quality-kit <gate> PROJECT=.. CONFIG=../quality-kit-generated DIRS="src tests" TEST="vendor/bin/pest"`.

Gate-specific traps that took work to satisfy:

- The kit analyses both `src` and `tests`, so `tests/Pest.php` must contain real code (a shared `require_once`) and test classes must stay at ≤ 6 methods (Mago `too-many-methods`); classes at ≤ 15 cyclomatic complexity.
- PHPStan gate has no exclusion for fixtures, so rule fixtures live in top-level `fixtures/`, never under `src`/`tests`.
- Prettier checks `*.md`, `*.json` too: run `prettier --write` on `AGENTS.md`, `README.md`, `composer.json` after editing them.
- `make -C quality-kit fix` aborts on eslint when the project has no JS; format with the `prettier` service directly instead.

## Layout

- PSR-4: `BruceGitHub\VisibilityNamespaceRules\` → `src/`, `Tests\` → `tests/`.
- `src/NamespaceVisibilityResolver.php` — orchestration: decides the violation for a target/current namespace. Config type alias `NamespaceConfigArray` is declared here (imported by the rule).
- `src/NamespaceRule.php`, `src/NamespaceRuleSet.php`, `src/NamespaceRuleFactory.php` — value object, ordered rule index (owner/exposed/access queries) and builder.
- `src/NamespaceMatcher.php` — case-insensitive, boundary-aware namespace string helpers.
- `src/PrivateNamespaceRule.php` — thin AST rule: for each referenced name, asks the resolver and builds the error.
- `src/ClassReferenceExtractor.php` + `StatementReferenceExtractor.php` / `TypeReferenceExtractor.php` / `ExpressionReferenceExtractor.php` — split so each class stays under the Mago complexity/kan/methods thresholds. "Leaf" references only; nullable/union/intersection types are traversed recursively, so each reference is reported exactly once.
- `extension.neon` — `parametersSchema` + default `%namespaceVisibility%` + service registration. `phpstan.neon` merely includes it.
- `docker/Dockerfile` — standalone distribution image (bundled PHPStan), built **only** from `composer.json`, `composer.lock`, `src/` and `extension.neon`. `docker/entrypoint.sh` composes a temp config from `extension.neon` + the consumer config and forwards args. Keep the `COPY` list in sync when adding files.
- `.github/workflows/ci.yml` — tests/phpstan/php-cs-fixer on PHP 8.2–8.4. There is no registry publishing: consumers build `docker/Dockerfile` locally.
- `.opencode/skills/namespace-visibility/SKILL.md` — agent skill that mirrors the rule for tools without PHPStan: it reads the consumer's `namespace-visibility.neon` and applies the same `privateNamespace.access` semantics. Keep it in sync with `NamespaceVisibilityResolver`; it is not part of the Docker distribution.
- `tests/NamespaceVisibilityResolverTest.php` — Pest unit tests for the resolver.
- `tests/PrivateNamespaceRuleTest.php` / `PrivateByDefaultRuleTest.php` — `PHPStan\Testing\RuleTestCase` integration tests. They reference fixtures via `dirname(__DIR__) . '/fixtures/...'`.
- `fixtures/**` — rule fixtures, intentionally outside `src`/`tests` so the quality-kit PHPStan gate does not flag the deliberately-undefined classes. Files here must NOT match `*Test.php` or PHPUnit will try to run them.

## Configuration contract

- Consumer parameter: `namespaceVisibility` (schema in `extension.neon`). The service binds it via the constructor argument named `namespaceVisibility`; renaming one without the other silently disables configuration.
- Modes: `default: public` (unlisted = public, listed = private) and `default: private` (unlisted = private to its own tree, listed = public). A listed entry's `visibility` defaults to the opposite of `default`.
- `exposed` accepts FQCN or namespace prefixes; `allowed_namespaces` is the C# `InternalsVisibleTo` equivalent.
- Error identifier is `privateNamespace.access`; message format is asserted in the integration tests.

## Gotchas

- PHPStan **2.x is required**: `php-cs-fixer` pulls `nikic/php-parser` v5, which fatals PHPStan 1.x (`PhpParserDecorator` abstract `Parser::getTokens`). The `^1.11 || ^2.0` constraint exists for API compatibility, but a fresh install resolves 2.x.
- `rector` was removed: it was broken (phpdoc-parser mismatch) and is not part of the toolchain.
- Custom PHPStan parameters must be declared in `parametersSchema`, using Nette syntax `?key: type()` inside `structure({...})`; otherwise PHPStan aborts with "Unexpected item 'parameters › namespaceVisibility'".
