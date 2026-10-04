<?php

declare(strict_types=1);

namespace BruceGitHub\VisibilityNamespaceRules;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\TraitUse;

/**
 * Extracts class-like names from declaration statements: inheritance,
 * implemented/extended interfaces, used traits, catch types and attributes.
 */
final class StatementReferenceExtractor
{
    /**
     * @return list<Name>
     */
    public function extract(Node $node): array
    {
        if ($node instanceof Class_) {
            return $this->extractClass($node);
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

        return [];
    }

    /**
     * @return list<Name>
     */
    private function extractClass(Class_ $node): array
    {
        $names = $node->extends instanceof Name ? [$node->extends] : [];
        foreach ($node->implements as $implemented) {
            $names[] = $implemented;
        }

        return $names;
    }
}
