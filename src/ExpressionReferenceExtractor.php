<?php

declare(strict_types=1);

namespace BruceGitHub\VisibilityNamespaceRules;

use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Name;

/**
 * Extracts class-like names from expression nodes (`new`, `instanceof`,
 * static access).
 */
final class ExpressionReferenceExtractor
{
    /**
     * @return list<Name>
     */
    public function extract(Node $node): array
    {
        if (! $node instanceof New_
            && ! $node instanceof Instanceof_
            && ! $node instanceof StaticCall
            && ! $node instanceof StaticPropertyFetch
            && ! $node instanceof ClassConstFetch
        ) {
            return [];
        }

        return $node->class instanceof Name ? [$node->class] : [];
    }
}
