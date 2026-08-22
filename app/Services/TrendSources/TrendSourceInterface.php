<?php
/**
 * EduPulse - Trend Source Adapter Interface
 * All external trend discovery sources must implement this interface.
 */

namespace App\Services\TrendSources;

interface TrendSourceInterface {

    /**
     * Get unique source identifier (e.g. 'google_trends', 'nta_rss', 'upsc_bulletin')
     */
    public function getSourceId(): string;

    /**
     * Get human-readable source label
     */
    public function getSourceName(): string;

    /**
     * Fetch emerging trends/topics from this source
     * 
     * @param int $limit Maximum number of candidate items to fetch
     * @return array List of normalized items: [
     *   [
     *     'keyword' => string,
     *     'source' => string,
     *     'url' => string|null,
     *     'trend_score' => int (0-100),
     *     'category_hint' => string|null,
     *     'snippet' => string,
     *     'detected_at' => string (Y-m-d H:i:s),
     *     'raw_payload' => array
     *   ]
     * ]
     */
    public function fetch(int $limit = 10): array;
}
