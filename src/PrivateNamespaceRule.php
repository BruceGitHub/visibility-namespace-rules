<?php

declare(strict_types=1);

namespace BruceGitHub\VisibilityNamespaceRules;

use PhpParser\Node;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Enforces namespace-scoped visibility for classes, interfaces, traits and
 * enums, approximating C# `internal` + `InternalsVisibleTo` and the proposals
 * from https://wiki.php.net/rfc/namespace-visibility and
 * https://wiki.php.net/rfc/namespace_visibility.
 *
 * A namespace can be declared private, with selected symbols or sub-namespaces
 * exposed publicly and selected external namespaces granted access.
 *
 * @phpstan-import-type NamespaceConfigArray from NamespaceVisibilityResolver
 *
 * @implements Rule<Node>
 */
final class PrivateNamespaceRule implements Rule
{
    private NamespaceVisibilityResolver $resolver;

    private ClassReferenceExtractor $extractor;

    /**
     * @param NamespaceConfigArray $namespaceVisibility
     */
    public function __construct(array $namespaceVisibility = [])
    {
        $this->resolver = new NamespaceVisibilityResolver($namespaceVisibility);
        $this->extractor = new ClassReferenceExtractor();
    }

    public function getNodeType(): string
    {
        return Node::class;
    }

    /**
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        foreach ($this->extractor->extract($node) as $name) {
            $error = $this->checkReference($name, $scope);
            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    private function checkReference(Name $name, Scope $scope): ?RuleError
    {
        if ($this->isSpecialName($name)) {
            return null;
        }

        $currentNamespace = $scope->getNamespace() ?? '';
        $violation = $this->resolver->findViolation($scope->resolveName($name), $currentNamespace);
        if ($violation === null) {
            return null;
        }

        return RuleErrorBuilder::message(sprintf(
            "Access to private namespace '%s' is not allowed from namespace '%s'.",
            $this->displayNamespace($violation),
            $this->displayNamespace($currentNamespace),
        ))
            ->line($name->getStartLine())
            ->identifier('privateNamespace.access')
            ->build();
    }

    private function isSpecialName(Name $name): bool
    {
        return \in_array(strtolower($name->toString()), ['self', 'static', 'parent'], strict: true);
    }

    private function displayNamespace(string $namespace): string
    {
        return $namespace === '' ? '{global}' : $namespace;
    }
}
