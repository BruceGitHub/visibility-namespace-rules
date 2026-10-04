<?php

declare(strict_types=1);

namespace Tests;

use BruceGitHub\VisibilityNamespaceRules\PrivateNamespaceRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * "Public by default": namespaces are public unless explicitly configured as
 * private. `App\Internal` is private, with an exposed public API and a friend
 * namespace.
 *
 * @extends RuleTestCase<PrivateNamespaceRule>
 */
final class PrivateNamespaceRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new PrivateNamespaceRule([
            'default' => 'public',
            'namespaces' => [
                'App\Internal' => [
                    'exposed' => [
                        'App\Internal\Api',
                        'App\Internal\Contracts\ClientInterface',
                    ],
                    'allowed_namespaces' => ['App\Tests'],
                ],
            ],
        ]);
    }

    public function testAllowedReferencesProduceNoErrors(): void
    {
        $this->analyse([dirname(__DIR__) . '/fixtures/public/internal.php'], []);
        $this->analyse([dirname(__DIR__) . '/fixtures/public/exposed.php'], []);
        $this->analyse([dirname(__DIR__) . '/fixtures/public/friend.php'], []);
    }

    public function testBlockedReferencesAreReported(): void
    {
        $message = rule_error(privateNamespace: 'App\Internal', currentNamespace: 'App\Api');

        $this->analyse([dirname(__DIR__) . '/fixtures/public/blocked.php'], [
            [$message, 9],
            [$message, 10],
            [$message, 11],
        ]);

        $this->analyse([dirname(__DIR__) . '/fixtures/public/global.php'], [
            [rule_error(privateNamespace: 'App\Internal', currentNamespace: '{global}'), 5],
        ]);

        $this->analyse([dirname(__DIR__) . '/fixtures/public/references.php'], [
            [$message, 14],
            [$message, 14],
            [$message, 16],
            [$message, 18],
            [$message, 22],
            [$message, 22],
            [$message, 22],
            [$message, 24],
            [$message, 25],
            [$message, 29],
            [$message, 30],
            [$message, 13],
        ]);
    }
}
