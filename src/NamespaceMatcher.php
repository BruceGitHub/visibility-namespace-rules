<?php

declare(strict_types=1);

namespace BruceGitHub\VisibilityNamespaceRules;

/**
 * String helpers for namespace names.
 *
 * All comparison is case-insensitive and boundary-aware: "App\Internal\Foo"
 * is within "App\Internal", while "App\InternalStuff" is not.
 */
final class NamespaceMatcher
{
    private function __construct()
    {
    }

    public static function normalize(string $namespace): string
    {
        return trim(string: $namespace, characters: '\\');
    }

    public static function namespaceOf(string $target): string
    {
        $position = strrpos(haystack: $target, needle: '\\');

        return $position === false ? '' : substr(string: $target, offset: 0, length: $position);
    }

    /**
     * @param list<string> $namespaces
     *
     * @return list<string>
     */
    public static function normalizeList(array $namespaces): array
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

    public static function isWithin(string $namespace, string $prefix): bool
    {
        if ($namespace === '' || $prefix === '') {
            return false;
        }

        if (strcasecmp($namespace, $prefix) === 0) {
            return true;
        }

        return strncasecmp($namespace, $prefix . '\\', \strlen($prefix) + 1) === 0;
    }
}
