<?php

declare(strict_types=1);

namespace BruceGitHub\VisibilityNamespaceRules;

/**
 * Ordered collection of configured namespaces and the queries the resolver
 * needs: which rule owns a symbol and whether a symbol is explicitly exposed.
 */
final class NamespaceRuleSet
{
    /** @var list<NamespaceRule> sorted by longest namespace first */
    private array $rules;

    /**
     * @param list<NamespaceRule> $rules
     */
    public function __construct(array $rules)
    {
        usort(
            $rules,
            static fn (NamespaceRule $a, NamespaceRule $b): int => \strlen($b->namespace) <=> \strlen($a->namespace),
        );

        $this->rules = $rules;
    }

    public function findOwner(string $target): ?NamespaceRule
    {
        foreach ($this->rules as $rule) {
            if (NamespaceMatcher::isWithin($target, $rule->namespace)) {
                return $rule;
            }
        }

        return null;
    }

    public function isExposed(string $target, NamespaceRule $owner): bool
    {
        foreach ($owner->exposed as $exposed) {
            if (strcasecmp($target, $exposed) === 0 || NamespaceMatcher::isWithin($target, $exposed)) {
                return true;
            }
        }

        return false;
    }

    public function grantsAccess(NamespaceRule $owner, string $currentNamespace): bool
    {
        if (NamespaceMatcher::isWithin($currentNamespace, $owner->namespace)) {
            return true;
        }

        foreach ($owner->allowedNamespaces as $allowed) {
            if (NamespaceMatcher::isWithin($currentNamespace, $allowed)) {
                return true;
            }
        }

        return false;
    }
}
