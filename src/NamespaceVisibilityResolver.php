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
 *
 * @phpstan-type NamespaceConfigArray array{
 *     default?: 'public'|'private',
 *     namespaces?: array<string, array{
 *         visibility?: 'public'|'private',
 *         exposed?: list<string>,
 *         allowed_namespaces?: list<string>
 *     }>
 * }
 */
final class NamespaceVisibilityResolver
{
    public const PUBLIC = 'public';
    public const PRIVATE = 'private';

    private bool $defaultIsPublic;

    private NamespaceRuleSet $ruleSet;

    /**
     * @param NamespaceConfigArray $config
     */
    public function __construct(array $config = [])
    {
        $this->defaultIsPublic = ($config['default'] ?? self::PUBLIC) !== self::PRIVATE;

        $factory = new NamespaceRuleFactory($this->defaultIsPublic);
        $this->ruleSet = new NamespaceRuleSet($factory->build($config['namespaces'] ?? []));
    }

    /**
     * Returns the private namespace denying access to $target from
     * $currentNamespace, or null when access is allowed.
     */
    public function findViolation(string $target, string $currentNamespace): ?string
    {
        $target = NamespaceMatcher::normalize($target);
        $currentNamespace = NamespaceMatcher::normalize($currentNamespace);

        if ($target === '') {
            return null;
        }

        $owner = $this->ruleSet->findOwner($target);

        if ($owner === null) {
            return $this->violationForUnconfigured($target, $currentNamespace);
        }

        if ($this->ruleSet->isExposed($target, $owner) || $owner->isPublic) {
            return null;
        }

        return $this->ruleSet->grantsAccess($owner, $currentNamespace) ? null : $owner->namespace;
    }

    private function violationForUnconfigured(string $target, string $currentNamespace): ?string
    {
        if ($this->defaultIsPublic) {
            return null;
        }

        $implicit = NamespaceMatcher::namespaceOf($target);
        if ($implicit === '') {
            return $currentNamespace === '' ? null : '';
        }

        return NamespaceMatcher::isWithin($currentNamespace, $implicit) ? null : $implicit;
    }
}
