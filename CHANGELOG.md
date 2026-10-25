# visibility-namespace-rules

## 0.1.0

### Minor Changes

- 6c3eed4: Initial pre-release of the namespace visibility rules:

  - `default: public` (opt-in private) and `default: private` (opt-in public) modes;
  - selective `exposed` symbols and sub-namespaces;
  - `allowed_namespaces` friend namespaces, the C# `InternalsVisibleTo` equivalent;
  - `privateNamespace.access` errors for class-like references (instantiation, `instanceof`,
    static access, `extends`/`implements`/`use`, `catch`, parameter/return/property types and
    attributes);
  - a standalone Docker image bundling PHPStan, and a `namespace-visibility` agent skill that
    mirrors the same semantics.
