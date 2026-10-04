<?php

declare(strict_types=1);

namespace BruceGitHub\VisibilityNamespaceRules;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\UnionType;
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
 * @implements Rule<Node>
 */
final class PrivateNamespaceRule implements Rule
{
    private NamespaceVisibilityResolver $resolver;

    /**
     * @param array{
     *     default?: 'public'|'private',
     *     namespaces?: array<string, array{
     *         visibility?: 'public'|'private',
     *         exposed?: list<string>,
     *         allowed_namespaces?: list<string>
     *     }>
     * } $namespaceVisibility
     */
    public function __construct(array $namespaceVisibility = [])
    {
        $this->resolver = new NamespaceVisibilityResolver($namespaceVisibility);
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

        foreach ($this->getClassReferences($node) as $name) {
            $shortName = strtolower($name->toString());
            if ($shortName === 'self' || $shortName === 'static' || $shortName === 'parent') {
                continue;
            }

            $resolved = $scope->resolveName($name);

            $currentNamespace = $scope->getNamespace() ?? '';
            $violation = $this->resolver->findViolation($resolved, $currentNamespace);
            if ($violation === null) {
                continue;
            }

            $line = $name->getStartLine() > 0 ? $name->getStartLine() : $node->getLine();

            $errors[] = RuleErrorBuilder::message(sprintf(
                "Access to private namespace '%s' is not allowed from namespace '%s'.",
                $violation === '' ? '{global}' : $violation,
                $currentNamespace === '' ? '{global}' : $currentNamespace,
            ))
                ->line($line)
                ->identifier('privateNamespace.access')
                ->build();
        }

        return $errors;
    }

    /**
     * Extracts all class-like names directly referenced by the given node.
     *
     * Only "leaf" reference nodes are handled. Type wrappers (nullable, union,
     * intersection) are traversed recursively here, never matched on their own,
     * so each reference is reported exactly once.
     *
     * @return list<Name>
     */
    private function getClassReferences(Node $node): array
    {
        if (
            $node instanceof New_
            || $node instanceof Instanceof_
            || $node instanceof StaticCall
            || $node instanceof StaticPropertyFetch
            || $node instanceof ClassConstFetch
        ) {
            return $node->class instanceof Name ? [$node->class] : [];
        }

        if ($node instanceof Class_) {
            $names = $node->extends instanceof Name ? [$node->extends] : [];
            foreach ($node->implements as $implemented) {
                $names[] = $implemented;
            }

            return $names;
        }

        if ($node instanceof Interface_) {
            return array_values($node->extends);
        }

        if ($node instanceof Enum_) {
            return array_values($node->implements);
        }

        if ($node instanceof TraitUse) {
            return array_values($node->traits);
        }

        if ($node instanceof Catch_) {
            return array_values($node->types);
        }

        if ($node instanceof Attribute) {
            return [$node->name];
        }

        if (
            $node instanceof ClassMethod
            || $node instanceof Function_
            || $node instanceof Closure
            || $node instanceof ArrowFunction
        ) {
            $names = $this->extractTypeNames($node->returnType);
            foreach ($node->params as $param) {
                $names = array_merge($names, $this->extractTypeNames($param->type));
            }

            return $names;
        }

        if ($node instanceof Property) {
            return $this->extractTypeNames($node->type);
        }

        return [];
    }

    /**
     * @return list<Name>
     */
    private function extractTypeNames(?Node $type): array
    {
        if ($type === null) {
            return [];
        }

        if ($type instanceof Name) {
            return [$type];
        }

        if ($type instanceof NullableType) {
            return $this->extractTypeNames($type->type);
        }

        if ($type instanceof UnionType || $type instanceof IntersectionType) {
            $names = [];
            foreach ($type->types as $inner) {
                $names = array_merge($names, $this->extractTypeNames($inner));
            }

            return $names;
        }

        return [];
    }
}
