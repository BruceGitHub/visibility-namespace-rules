<?php

declare(strict_types=1);

namespace BruceGitHub\VisibilityNamespaceRules;

/**
 * Builds the ordered list of {@see NamespaceRule} from raw configuration.
 */
final class NamespaceRuleFactory
{
    private bool $defaultIsPublic;

    public function __construct(bool $defaultIsPublic)
    {
        $this->defaultIsPublic = $defaultIsPublic;
    }

    /**
     * @param array<string, array{
     *     visibility?: 'public'|'private',
     *     exposed?: list<string>,
     *     allowed_namespaces?: list<string>
     * }> $namespaces
     *
     * @return list<NamespaceRule>
     */
    public function build(array $namespaces): array
    {
        $rules = [];
        foreach ($namespaces as $namespace => $definition) {
            $prefix = NamespaceMatcher::normalize((string) $namespace);
            if ($prefix === '') {
                continue;
            }

            $rules[] = $this->buildRule($prefix, $definition);
        }

        return $rules;
    }

    /**
     * @param array{
     *     visibility?: 'public'|'private',
     *     exposed?: list<string>,
     *     allowed_namespaces?: list<string>
     * } $definition
     */
    private function buildRule(string $prefix, array $definition): NamespaceRule
    {
        $visibility = $definition['visibility'] ?? ($this->defaultIsPublic ? NamespaceVisibilityResolver::PRIVATE : NamespaceVisibilityResolver::PUBLIC);

        return new NamespaceRule(
            $prefix,
            $visibility === NamespaceVisibilityResolver::PUBLIC,
            NamespaceMatcher::normalizeList($definition['exposed'] ?? []),
            NamespaceMatcher::normalizeList($definition['allowed_namespaces'] ?? []),
        );
    }
}
