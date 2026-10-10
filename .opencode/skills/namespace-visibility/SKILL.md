---
name: namespace-visibility
description: Enforce namespace-scoped visibility (private namespaces, C#-style internal) by reading a namespace-visibility.neon config and reviewing PHP class-like references. Use when a project defines namespaceVisibility rules and you want to check them without running the PHPStan rule, or when asked to audit references against namespace visibility.
---

# Namespace Visibility (agent skill)

This skill reproduces the `visibility-namespace-rules` PHPStan rule **without
running PHPStan**. It reads the project's configuration and reports the same
violations the deterministic rule would report.

The deterministic rule remains the recommended option for CI (it is fast,
reproducible and cannot hallucinate). Use this skill when you want an
in-conversation check without a PHP toolchain.

## Step 1 — Find the configuration

Look for the `namespaceVisibility` parameter, in this order:

1. `namespace-visibility.neon` at the project root (path overridable with the
   `PHPSTAN_CONFIG` environment variable).
2. Any PHPStan config (`phpstan.neon`, `phpstan.neon.dist`, `phpstan.dist.neon`)
   that sets `parameters.namespaceVisibility`, directly or through `includes`.
3. If nothing is found, **stop and ask the user** for the config. Never invent
   rules.

The parameter shape:

```neon
parameters:
    namespaceVisibility:
        default: public # public | private
        namespaces:
            'App\Internal':
                visibility: private # optional
                exposed:
                    - 'App\Internal\Api'
                    - 'App\Internal\Contracts\ClientInterface'
                allowed_namespaces:
                    - 'App\Tests'
```

## Step 2 — Normalize the config into rules

- `default` is `public` unless it is exactly `private`.
- Every `namespaces` key is a namespace prefix; trim surrounding `\`.
- `visibility` defaults to the **opposite** of `default`: in `public` mode a
  listed entry is private, in `private` mode a listed entry is public.
- Trim the `exposed` / `allowed_namespaces` entries and drop empty ones.
- Sort rules by prefix length, **longest first** (the most specific prefix wins).

## Step 3 — Decide whether a reference is a violation

Mirror `NamespaceVisibilityResolver::findViolation(target, currentNamespace)`
exactly:

1. Normalize both names (trim `\`). An empty target is always allowed.
2. Find the **owning rule**: the first rule (longest prefix first) whose prefix
   contains the target.
3. If there is **no owner**:
   - default `public` → allowed;
   - default `private` → the target is private to its own namespace tree.
     Compute the target's namespace (everything before the last `\`) and allow
     when `currentNamespace` is within it; otherwise report that namespace. A
     target with no namespace (global) reports `{global}` unless the current
     namespace is also global.
4. If the target is **exposed** (equals an `exposed` entry case-insensitively,
   or is within it) → allowed.
5. If the owner is **public** → allowed.
6. If the owner is **private**, allow when `currentNamespace` is within the
   owner's namespace, or within any of the owner's `allowed_namespaces`.
7. Otherwise → violation on the owner's namespace.

All comparisons are case-insensitive and **boundary-aware**: `App\Internal\Foo`
is within `App\Internal`, while `App\InternalStuff` is not.

## Step 4 — Collect references

For every PHP file under the analysed paths (default `src`), inspect each
class-like reference together with the namespace it appears in:

- `new`, `instanceof`;
- static calls, static property access, `::class`;
- `extends`, `implements`, `use` (trait);
- `catch` types;
- parameter, return and property types, including nullable, union and
  intersection (recurse into each member);
- attributes.

Resolve each name to its FQCN using the file's `use` imports and the current
namespace. Ignore `self`, `static` and `parent`. A `use` import on its own is
**not** a reference; do not report it.

## Step 5 — Report

Match the deterministic rule's message on every violation:

```
Access to private namespace '<violation>' is not allowed from namespace '<current>'.
```

- `<violation>` is the private namespace (the global namespace is shown as
  `{global}`);
- `<current>` is the namespace of the referencing code, also `{global}` when
  there is none;
- keep the identifier `privateNamespace.access` in any machine-readable output.

Include the file, line and offending symbol, and group the results by file.

## Fidelity rules

- Report **every** occurrence, not just the first.
- Never invent, relax or skip rules: if the config is missing or ambiguous, stop
  and ask.
- This is static, best-effort analysis: reflection, dynamic string class names
  and generated code are out of scope, exactly like the PHPStan rule. State that
  caveat when the result matters.
