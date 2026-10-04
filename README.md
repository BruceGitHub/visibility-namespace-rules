# visibility-namespace-rules

[![CI](https://github.com/BruceGitHub/visibility-namespace-rules/actions/workflows/ci.yml/badge.svg)](https://github.com/BruceGitHub/visibility-namespace-rules/actions/workflows/ci.yml)

PHPStan rules for **namespace-scoped visibility**: mark a namespace as *private*
and decide exactly which classes, interfaces, traits or sub-namespaces stay
open — plus which external namespaces are allowed in.

It brings C#-style `internal` (and `InternalsVisibleTo`) to PHP, statically, and
is inspired by the namespace visibility proposals:

- [`rfc/namespace-visibility`](https://wiki.php.net/rfc/namespace-visibility) (2018) — class/interface/trait visibility.
- [`rfc/namespace_visibility`](https://wiki.php.net/rfc/namespace_visibility) (2025) — `private(namespace)` for members.

PHP does not support namespace visibility natively, so this package enforces the
boundaries with static analysis instead.

## How it compares to `@internal`

PHPStan already understands the `@internal` annotation, but it is a coarse,
file/namespace-root based signal. This package gives you:

- **visibility modes** — "public by default" (opt-in private) or "private by default" (opt-in public);
- **selective export** — keep a namespace private but expose individual symbols or sub-namespaces publicly;
- **friend namespaces** — let specific external namespaces through, like C# `InternalsVisibleTo`;
- **namespace-tree awareness** — access stays allowed inside the private namespace tree.

## Installation

The recommended way is the standalone Docker image: it bundles PHPStan and these
rules, so your project keeps its own PHPStan version and cannot hit dependency
conflicts.

### Docker (recommended)

Build the image once from this repository:

```bash
git clone https://github.com/BruceGitHub/visibility-namespace-rules.git
cd visibility-namespace-rules
docker build -f docker/Dockerfile -t visibility-namespace-rules .
```

Add a `namespace-visibility.neon` to your project root:

```neon
parameters:
    namespaceVisibility:
        default: public
        namespaces:
            'App\Internal':
                exposed:
                    - 'App\Internal\Api'
                allowed_namespaces:
                    - 'App\Tests'
```

Then run it from your project root:

```bash
docker run --rm -v "$PWD":/app -w /app \
    visibility-namespace-rules src
```

The entrypoint builds a temporary PHPStan config that includes these rules plus
your `namespace-visibility.neon`, and runs `phpstan analyse`. Extra arguments
are forwarded to PHPStan (e.g. `src tests` or `--error-format=json`).

| Environment variable | Default | Description |
| --- | --- | --- |
| `PHPSTAN_CONFIG` | `/app/namespace-visibility.neon` if present | Config defining `namespaceVisibility`. |
| `PHPSTAN_LEVEL` | `max` | PHPStan rule level. |
| `PHPSTAN_MEMORY_LIMIT` | PHPStan default | e.g. `1G`. |

The rule picks up your project's autoloader automatically
(`/app/vendor/autoload.php`) when present, so symbols resolve. Want to mount only
a sub-directory? Use `-v "$PWD/src":/app/src -w /app`.

### Composer package

If you prefer Composer and control your own PHPStan, install the package and
include `extension.neon`:

```bash
composer require --dev brucegithub/visibility-namespace-rules
```

With [`phpstan/extension-installer`](https://github.com/phpstan/extension-installer)
it is registered automatically; otherwise include it manually:

```neon
includes:
    - vendor/brucegithub/visibility-namespace-rules/extension.neon
```

## Configuration

All options live under the `namespaceVisibility` parameter.

### Public by default (opt-in private)

Namespaces are public unless listed. A listed namespace is **private** unless
you say otherwise.

```neon
parameters:
    namespaceVisibility:
        default: public
        namespaces:
            'App\Internal':
                exposed:
                    - 'App\Internal\Api'                                # whole sub-namespace
                    - 'App\Internal\Contracts\ClientInterface'          # a single symbol
                allowed_namespaces:
                    - 'App\Tests'                                       # friend namespaces
```

### Private by default (opt-in public)

Every namespace is private to its own namespace tree unless listed. A listed
namespace is **public** unless you say otherwise.

```neon
parameters:
    namespaceVisibility:
        default: private
        namespaces:
            'App\Api':
                visibility: public
            'App\Internal':
                visibility: private
                allowed_namespaces:
                    - 'App\Tests'
```

### Reference

| Key | Type | Description |
| --- | --- | --- |
| `default` | `public` \| `private` | Visibility for namespaces that are not listed. Defaults to `public`. |
| `namespaces` | map | Per-namespace configuration keyed by namespace prefix. |
| `namespaces.<ns>.visibility` | `public` \| `private` | Overrides the entry visibility. Defaults to the opposite of `default` (so listing a namespace in `public` mode makes it private, and vice versa). |
| `namespaces.<ns>.exposed` | list | Classes/interfaces/traits/enums or sub-namespace prefixes that are reachable from **everywhere**, even though the namespace is private. |
| `namespaces.<ns>.allowed_namespaces` | list | External namespace prefixes allowed to reference the private namespace (C# `InternalsVisibleTo`). |

Rules applied to a referenced symbol:

1. If it matches an `exposed` entry of its namespace → allowed everywhere.
2. If its namespace is public → allowed.
3. If its namespace is private:
   - code in the same namespace **tree** is allowed;
   - code in an `allowed_namespaces` prefix is allowed;
   - otherwise it is reported.

The most specific configured prefix wins, matching is boundary-aware
(`App\Internal` does **not** match `App\InternalStuff`) and case-insensitive.

## What is analyzed

Every class-like reference is checked:

- instantiation (`new`), `instanceof`;
- static calls / static property access / `::class`;
- `extends`, `implements`, `use` (trait);
- `catch` types;
- parameter, return and property types (including nullable, union and intersection);
- attributes.

`self`, `static` and `parent` are ignored. `use` imports alone are not reported —
only actual references are.

## Limitations

- Static analysis only: reflection, string class names and dynamic code are out of scope.
- Enforces the visibility of class-like symbols, not of their members (see the 2025 RFC for that).
- Undefined classes are still reported by PHPStan's own rules; this package only adds the namespace boundary check.

## Development

There is no need for a local PHP toolchain: everything runs in Docker.

```bash
docker compose up -d --build
docker compose exec app composer install

docker compose exec app vendor/bin/pest            # tests
docker compose exec app vendor/bin/phpstan analyse  # static analysis
docker compose exec app vendor/bin/php-cs-fixer fix --allow-risky=yes
```

## License

MIT — see [LICENSE](LICENSE).
