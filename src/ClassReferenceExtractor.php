<?php

declare(strict_types=1);

namespace BruceGitHub\VisibilityNamespaceRules;

use PhpParser\Node;
use PhpParser\Node\Name;

/**
 * Extracts the class-like names a single AST node directly references by
 * delegating to the specialised extractors.
 */
final class ClassReferenceExtractor
{
    private StatementReferenceExtractor $statements;

    private TypeReferenceExtractor $types;

    private ExpressionReferenceExtractor $expressions;

    public function __construct()
    {
        $this->statements = new StatementReferenceExtractor();
        $this->types = new TypeReferenceExtractor();
        $this->expressions = new ExpressionReferenceExtractor();
    }

    /**
     * @return list<Name>
     */
    public function extract(Node $node): array
    {
        $names = $this->statements->extract($node);
        if ($names !== []) {
            return $names;
        }

        $names = $this->types->extractFromNode($node);
        if ($names !== []) {
            return $names;
        }

        return $this->expressions->extract($node);
    }
}
