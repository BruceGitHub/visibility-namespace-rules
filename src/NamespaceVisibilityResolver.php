<?php

declare(strict_types=1);

namespace BruceGitHub\VisibilityNamespaceRules;

/**
 * Resolves whether a class-like symbol is reachable from a given namespace.
 *
 * Supported configuration, under the `namespaceVisibility` parameter:
 *
 *     default: public            # "public by default" (opt-in private)
 *     namespaces:
 *         'App\Internal':
 *             visibility: private # optional; defaults to the opposite of `default`
 *             exposed:            # symbols/sub-namespaces open to everyone
 *                 - 'App\Internal\Api'
 *                 - 'App\Internal\Contracts\ClientInterface'
 *             allowed_namespaces: # external namespaces allowed to access (InternalsVisibleTo)
 *                 - 'App\Tests'
 *
 * With `default: private` ("private by default") every unlisted namespace is
 * private to its own namespace tree, and listed namespaces are public unless
 * configured otherwise.
 */
final class NamespaceVisibilityResolver
{
    public const PUBLIC = 'public';
    public const PRIVATE = 'private';

    private bool $defaultIsPublic;

    /** @var list<NamespaceRule> sorted by longest namespace first */
    private array $rules;

    /**
     * @param array{
     *     default?: 'public'|'private',
     *     namespaces?: array<string, array{
     *         visibility?: 'public'|'private',
     *         exposed?: list<string>,
     *         allowed_namespaces?: list<string>
     *     }>
     * } $config
     */
    public function __construct(array $config = [])
    {
        $this->defaultIsPublic = ($config['default'] ?? self::PUBLIC) !== self::PRIVATE;

        $rules = [];
        foreach ($config['namespaces'] ?? [] as $namespace => $definition) {
            $prefix = self::normalize((string) $namespace);
            if ($prefix === '') {
                continue;
            }

            $visibility = $definition['visibility'] ?? ($this->defaultIsPublic ? self::PRIVATE : self::PUBLIC);

            $rules[] = new NamespaceRule(
                $prefix,
                $visibility === self::PUBLIC,
                self::normalizeList($definition['exposed'] ?? []),
                self::normalizeList($definition['allowed_namespaces'] ?? []),
            );
        }

        usort(
            $rules,
            static fn (NamespaceRule $a, NamespaceRule $b): int => \strlen($b->namespace) <=> \strlen($a->namespace),
        );

        $this->rules = $rules;
    }

    /**
     * Returns the private namespace denying access to $target from
     * $currentNamespace, or null when access is allowed.
     */
    public function findViolation(string $target, string $currentNamespace): ?string
    {
        $target = self::normalize($target);
        $currentNamespace = self::normalize($currentNamespace);

        if ($target === '') {
            return null;
        }

        $owner = $this->findOwner($target);

        if ($owner === null) {
            if ($this->defaultIsPublic) {
                return null;
            }

            // "Private by default": an unconfigured namespace is private to
            // its own namespace tree (the namespace the symbol lives in).
            $implicit = self::namespaceOf($target);

            if ($implicit === '') {
                return $currentNamespace === '' ? null : '';
            }

            return self::isWithin($currentNamespace, $implicit) ? null : $implicit;
        }

        if ($this->isExposed($target, $owner)) {
            return null;
        }

        if ($owner->isPublic) {
            return null;
        }

        if (self::isWithin($currentNamespace, $owner->namespace)) {
            return null;
        }

        foreach ($owner->allowedNamespaces as $allowed) {
            if (self::isWithin($currentNamespace, $allowed)) {
                return null;
            }
        }

        return $owner->namespace;
    }

    private function findOwner(string $target): ?NamespaceRule
    {
        foreach ($this->rules as $rule) {
            if (self::isWithin($target, $rule->namespace)) {
                return $rule;
            }
        }

        return null;
    }

    private function isExposed(string $target, NamespaceRule $owner): bool
    {
        foreach ($owner->exposed as $exposed) {
            if (strcasecmp($target, $exposed) === 0 || self::isWithin($target, $exposed)) {
                return true;
            }
        }

        return false;
    }

    private static function namespaceOf(string $target): string
    {
        $position = strrpos($target, '\\');

        return $position === false ? '' : substr($target, 0, $position);
    }

    /**
     * Boundary-aware, case-insensitive namespace containment check.
     *
     * "App\Internal\Foo" is within "App\Internal"; "App\InternalStuff" is not.
     */
    private static function isWithin(string $namespace, string $prefix): bool
    {
        if ($namespace === '' || $prefix === '') {
            return false;
        }

        if (strcasecmp($namespace, $prefix) === 0) {
            return true;
        }

        return strncasecmp($namespace, $prefix . '\\', \strlen($prefix) + 1) === 0;
    }

    private static function normalize(string $namespace): string
    {
        return trim($namespace, '\\');
    }

    /**
     * @param list<string> $namespaces
     *
     * @return list<string>
     */
    private static function normalizeList(array $namespaces): array
    {
        $normalized = [];
        foreach ($namespaces as $namespace) {
            $value = self::normalize($namespace);
            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return $normalized;
    }
}
