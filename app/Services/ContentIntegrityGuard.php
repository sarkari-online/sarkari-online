<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class ContentLossException extends RuntimeException {}

/**
 * ContentIntegrityGuard
 * 
 * Guards against structural content degradation (tables, h2 headings)
 * during post-processing injection, sanitization, or healing pipelines.
 */
final class ContentIntegrityGuard
{
    /**
     * Assert that post-processing did not reduce essential structural elements.
     *
     * @param string $before Content before post-processing
     * @param string $after  Content after post-processing
     * @throws ContentLossException If any structural count decreased
     */
    public static function assertNoStructuralLoss(string $before, string $after): void
    {
        $checks = [
            'table' => ['<table', substr_count($before, '<table'), substr_count($after, '<table')],
            'h2'    => ['<h2',    substr_count($before, '<h2'),    substr_count($after, '<h2')],
        ];

        foreach ($checks as $label => [$tag, $beforeCount, $afterCount]) {
            if ($afterCount < $beforeCount) {
                throw new ContentLossException(
                    "ContentIntegrityGuard: Post-processing reduced {$label} count from {$beforeCount} to {$afterCount} — blocking publish"
                );
            }
        }
    }
}
