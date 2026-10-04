<?php

declare(strict_types=1);

namespace Tests;

use BruceGitHub\VisibilityNamespaceRules\NamespaceVisibilityResolver;

test('public by default allows unconfigured namespaces', function (): void {
    $resolver = new NamespaceVisibilityResolver(['default' => 'public']);

    expect($resolver->findViolation('App\Whatever\Thing', 'App\Other'))->toBeNull();
});

test('public by default blocks a configured private namespace from the outside', function (): void {
    $resolver = new NamespaceVisibilityResolver([
        'default' => 'public',
        'namespaces' => ['App\Internal' => []],
    ]);

    expect($resolver->findViolation('App\Internal\Foo', 'App\Api'))->toBe('App\Internal');
});

test('a private namespace is reachable from anywhere in its own tree', function (): void {
    $resolver = new NamespaceVisibilityResolver([
        'default' => 'public',
        'namespaces' => ['App\Internal' => []],
    ]);

    expect($resolver->findViolation('App\Internal\Foo', 'App\Internal'))->toBeNull()
        ->and($resolver->findViolation('App\Internal\Foo', 'App\Internal\Sub\Deep'))->toBeNull();
});

test('exposed classes are reachable from the outside', function (): void {
    $resolver = new NamespaceVisibilityResolver([
        'default' => 'public',
        'namespaces' => [
            'App\Internal' => ['exposed' => ['App\Internal\Contracts\ClientInterface']],
        ],
    ]);

    expect($resolver->findViolation('App\Internal\Contracts\ClientInterface', 'App\Api'))->toBeNull();
});

test('exposed sub-namespaces grant access to every class below them', function (): void {
    $resolver = new NamespaceVisibilityResolver([
        'default' => 'public',
        'namespaces' => [
            'App\Internal' => ['exposed' => ['App\Internal\Api']],
        ],
    ]);

    expect($resolver->findViolation('App\Internal\Api\Widget', 'App\Api'))->toBeNull()
        ->and($resolver->findViolation('App\Internal\Api\Nested\Deep', 'App\Api'))->toBeNull()
        ->and($resolver->findViolation('App\Internal\ApiOther\Widget', 'App\Api'))->toBe('App\Internal');
});

test('friend namespaces are allowed to reach private symbols', function (): void {
    $resolver = new NamespaceVisibilityResolver([
        'default' => 'public',
        'namespaces' => [
            'App\Internal' => ['allowed_namespaces' => ['App\Tests']],
        ],
    ]);

    expect($resolver->findViolation('App\Internal\Foo', 'App\Tests\Support'))->toBeNull()
        ->and($resolver->findViolation('App\Internal\Foo', 'App\Api'))->toBe('App\Internal');
});

test('prefix matching respects namespace boundaries', function (): void {
    $resolver = new NamespaceVisibilityResolver([
        'default' => 'public',
        'namespaces' => ['App\Internal' => []],
    ]);

    expect($resolver->findViolation('App\InternalStuff\Foo', 'App\Api'))->toBeNull();
});

test('namespace matching is case-insensitive', function (): void {
    $resolver = new NamespaceVisibilityResolver([
        'default' => 'public',
        'namespaces' => ['App\Internal' => []],
    ]);

    expect($resolver->findViolation('app\internal\Foo', 'APP\API'))->toBe('App\Internal');
});

test('the most specific private prefix wins', function (): void {
    $resolver = new NamespaceVisibilityResolver([
        'default' => 'public',
        'namespaces' => [
            'App' => ['visibility' => 'private'],
            'App\Internal' => ['allowed_namespaces' => ['App\Api']],
        ],
    ]);

    expect($resolver->findViolation('App\Internal\Foo', 'App\Api'))->toBeNull();
});

test('private by default blocks unconfigured namespaces across trees', function (): void {
    $resolver = new NamespaceVisibilityResolver(['default' => 'private']);

    expect($resolver->findViolation('App\Other\Widget', 'App\Filesystem'))->toBe('App\Other');
});

test('private by default allows unconfigured namespaces within the same tree', function (): void {
    $resolver = new NamespaceVisibilityResolver(['default' => 'private']);

    expect($resolver->findViolation('App\Other\Widget', 'App\Other\Sub'))->toBeNull();
});

test('private by default allows explicitly public namespaces', function (): void {
    $resolver = new NamespaceVisibilityResolver([
        'default' => 'private',
        'namespaces' => [
            'App\Api' => ['visibility' => 'public'],
        ],
    ]);

    expect($resolver->findViolation('App\Api\Widget', 'App\Filesystem'))->toBeNull();
});

test('private by default treats global classes as private to the global namespace', function (): void {
    $resolver = new NamespaceVisibilityResolver(['default' => 'private']);

    expect($resolver->findViolation('LegacyClass', ''))->toBeNull()
        ->and($resolver->findViolation('LegacyClass', 'App\Api'))->toBe('');
});
