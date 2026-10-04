<?php

declare(strict_types=1);

namespace Tests;

/**
 * Builds the expected rule error message for a blocked namespace reference.
 */
function rule_error(string $privateNamespace, string $currentNamespace): string
{
    return sprintf(
        "Access to private namespace '%s' is not allowed from namespace '%s'.",
        $privateNamespace,
        $currentNamespace,
    );
}
