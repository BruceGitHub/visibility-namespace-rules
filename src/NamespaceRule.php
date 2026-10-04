<?php

declare(strict_types=1);

namespace BruceGitHub\VisibilityNamespaceRules;

/**
 * Normalized configuration for a single namespace prefix.
 */
final class NamespaceRule
{
    /**
     * @param list<string> $exposed
     * @param list<string> $allowedNamespaces
     */
    public function __construct(
        public readonly string $namespace,
        public readonly bool $isPublic,
        public readonly array $exposed,
        public readonly array $allowedNamespaces,
    ) {
    }
}
