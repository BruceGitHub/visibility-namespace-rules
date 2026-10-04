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

    public function testAccessFromOutsideIsBlocked(): void
    {
        $this->analyse([__DIR__ . '/data/public/blocked.php'], [
            [$this->message('App\Internal', 'App\Api'), 9],
            [$this->message('App\Internal', 'App\Api'), 10],
            [$this->message('App\Internal', 'App\Api'), 11],
        ]);
    }

    public function testAccessFromInsideThePrivateTreeIsAllowed(): void
    {
        $this->analyse([__DIR__ . '/data/public/internal.php'], []);
    }

    public function testExposedSymbolsAreAllowed(): void
    {
        $this->analyse([__DIR__ . '/data/public/exposed.php'], []);
    }

    public function testFriendNamespacesAreAllowed(): void
    {
        $this->analyse([__DIR__ . '/data/public/friend.php'], []);
    }

    public function testAccessFromTheGlobalNamespaceIsBlocked(): void
    {
        $this->analyse([__DIR__ . '/data/public/global.php'], [
            [$this->message('App\Internal', '{global}'), 5],
        ]);
    }

    public function testEveryReferenceKindIsReported(): void
    {
        $this->analyse([__DIR__ . '/data/public/references.php'], [
            [$this->message('App\Internal', 'App\Api'), 14],
            [$this->message('App\Internal', 'App\Api'), 14],
            [$this->message('App\Internal', 'App\Api'), 16],
            [$this->message('App\Internal', 'App\Api'), 18],
            [$this->message('App\Internal', 'App\Api'), 22],
            [$this->message('App\Internal', 'App\Api'), 22],
            [$this->message('App\Internal', 'App\Api'), 22],
            [$this->message('App\Internal', 'App\Api'), 24],
            [$this->message('App\Internal', 'App\Api'), 25],
            [$this->message('App\Internal', 'App\Api'), 29],
            [$this->message('App\Internal', 'App\Api'), 30],
            [$this->message('App\Internal', 'App\Api'), 13],
        ]);
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
