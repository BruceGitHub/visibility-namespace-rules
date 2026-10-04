<?php

declare(strict_types=1);

namespace BruceGitHub\VisibilityNamespaceRules;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\UnionType;

/**
 * Extracts class-like names from type declarations: property types and the
 * parameter/return types of methods, functions and closures.
 *
 * Type wrappers (nullable, union, intersection) are traversed recursively so
 * each referenced name is reported exactly once.
 */
final class TypeReferenceExtractor
{
    /**
     * @return list<Name>
     */
    public function extractFromNode(Node $node): array
    {
        if ($node instanceof Property) {
            return $this->extractFromType($node->type);
        }

        if ($node instanceof ClassMethod || $node instanceof Function_ || $node instanceof Closure || $node instanceof ArrowFunction) {
            return $this->extractFromCallable($node);
        }

        return [];
    }

    /**
     * @return list<Name>
     */
    private function extractFromCallable(ClassMethod|Function_|Closure|ArrowFunction $node): array
    {
        $names = $this->extractFromType($node->returnType);
        foreach ($node->params as $param) {
            $names = array_merge($names, $this->extractFromType($param->type));
        }

        return $names;
    }

    /**
     * @return list<Name>
     */
    private function extractFromType(?Node $type): array
    {
        if ($type instanceof Name) {
            return [$type];
        }

        if ($type instanceof NullableType) {
            return $this->extractFromType($type->type);
        }

        if ($type instanceof UnionType || $type instanceof IntersectionType) {
            return $this->extractFromUnion($type);
        }

        return [];
    }

    /**
     * @return list<Name>
     */
    private function extractFromUnion(UnionType|IntersectionType $type): array
    {
        $names = [];
        foreach ($type->types as $inner) {
            $names = array_merge($names, $this->extractFromType($inner));
        }

        return $names;
    }
}
