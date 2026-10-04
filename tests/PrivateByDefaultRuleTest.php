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

    public function testReachableReferencesProduceNoErrors(): void
    {
        $this->analyse([dirname(__DIR__) . '/fixtures/private/public_namespace.php'], []);
        $this->analyse([dirname(__DIR__) . '/fixtures/private/unlisted_same_tree.php'], []);
        $this->analyse([dirname(__DIR__) . '/fixtures/private/internal_friend.php'], []);
    }

    public function testBlockedReferencesAreReported(): void
    {
        $this->analyse([dirname(__DIR__) . '/fixtures/private/unlisted_cross.php'], [
            [rule_error(privateNamespace: 'App\Other', currentNamespace: 'App\Filesystem'), 9],
        ]);

        $this->analyse([dirname(__DIR__) . '/fixtures/private/internal_blocked.php'], [
            [rule_error(privateNamespace: 'App\Internal', currentNamespace: 'App\Filesystem'), 9],
        ]);
    }
}
