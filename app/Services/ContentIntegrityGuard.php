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

    /**
     * Assert intentional, counted removal of legacy corrupted tables.
     *
     * Requires the caller to state exactly how many tables it expected to remove.
     * If the actual decrease does not match that exact count, something removed
     * MORE or LESS than intended and this throws.
     *
     * @param string $before Content before removal
     * @param string $afterRemoval Content after removal
     * @param int $expectedRemovedCount Expected number of tables removed
     * @throws ContentLossException If actual removed count !== expected count or h2 count decreased
     */
    public static function assertIntentionalReplacement(string $before, string $afterRemoval, int $expectedRemovedCount): void
    {
        // Heading count must NEVER decrease
        if (substr_count($afterRemoval, '<h2') < substr_count($before, '<h2')) {
            throw new ContentLossException(
                "ContentIntegrityGuard: Intentional table removal accidentally decreased <h2> count — refusing to proceed"
            );
        }

        $beforeCount = substr_count($before, '<table');
        $afterCount  = substr_count($afterRemoval, '<table');
        $actualRemoved = $beforeCount - $afterCount;

        if ($actualRemoved !== $expectedRemovedCount) {
            throw new ContentLossException(
                "ContentIntegrityGuard: Expected to remove exactly {$expectedRemovedCount} legacy table(s), "
                . "but table count changed by {$actualRemoved} (before: {$beforeCount}, after: {$afterCount}) — refusing to proceed"
            );
        }
    }
}
