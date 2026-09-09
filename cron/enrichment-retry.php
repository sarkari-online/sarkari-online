<?php
/**
 * Cron: Enrichment Retry Worker
 * Checks held topics in 'needs_enrichment' and attempts targeted re-fetches.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Services\EnrichmentRetryService;

echo "[" . date('Y-m-d H:i:s') . "] Starting Enrichment Retry Cron...\n";

$service = new EnrichmentRetryService();
$result = $service->processHeldTopics(10);

echo "Completed: {$result['processed']} processed, {$result['retried']} enriched, {$result['escalated']} escalated to human review.\n";
