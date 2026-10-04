<?php

declare(strict_types=1);

namespace Tests;

use BruceGitHub\VisibilityNamespaceRules\PrivateNamespaceRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * "Private by default": every namespace is private to its own tree unless
 * explicitly made public or granted access.
 *
 * @extends RuleTestCase<PrivateNamespaceRule>
 */
final class PrivateByDefaultRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new PrivateNamespaceRule([
            'default' => 'private',
            'namespaces' => [
                'App\Api' => ['visibility' => 'public'],
                'App\Internal' => [
                    'visibility' => 'private',
                    'allowed_namespaces' => ['App\Tests'],
                ],
            ],
        ]);
    }

    public function testExplicitlyPublicNamespaceIsReachable(): void
    {
        $this->analyse([__DIR__ . '/data/private/public_namespace.php'], []);
    }

    public function testUnconfiguredNamespaceIsBlockedAcrossTrees(): void
    {
        $this->analyse([__DIR__ . '/data/private/unlisted_cross.php'], [
            [$this->message('App\Other', 'App\Filesystem'), 9],
        ]);
    }

    public function testUnconfiguredNamespaceIsReachableWithinItsTree(): void
    {
        $this->analyse([__DIR__ . '/data/private/unlisted_same_tree.php'], []);
    }

    public function testExplicitlyPrivateNamespaceIsBlocked(): void
    {
        $this->analyse([__DIR__ . '/data/private/internal_blocked.php'], [
            [$this->message('App\Internal', 'App\Filesystem'), 9],
        ]);
    }

    public function testFriendNamespaceIsReachable(): void
    {
        $this->analyse([__DIR__ . '/data/private/internal_friend.php'], []);
    }

    private function message(string $privateNamespace, string $currentNamespace): string
    {
        return sprintf(
            "Access to private namespace '%s' is not allowed from namespace '%s'.",
            $privateNamespace,
            $currentNamespace,
        );
    }
}
