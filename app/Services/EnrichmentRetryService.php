<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Helpers\Logger;
use Throwable;

final class EnrichmentRetryService
{
    private const MAX_AUTO_RETRIES = 4;
    private const RETRY_INTERVAL_HOURS = 6;

    /**
     * Process held topics that are awaiting enrichment.
     */
    public function processHeldTopics(int $limit = 5): array
    {
        $held = Database::fetchAll("
            SELECT id, keyword, status, raw_payload, created_at, updated_at 
            FROM trends 
            WHERE status = 'needs_enrichment'
            ORDER BY id ASC 
            LIMIT :lim
        ", ['lim' => $limit]);

        if (empty($held)) {
            return ['processed' => 0, 'retried' => 0, 'escalated' => 0];
        }

        $pipeline = new PipelineService();
        $retried = 0;
        $escalated = 0;

        foreach ($held as $trend) {
            $trendId = (int)$trend['id'];
            $payload = !empty($trend['raw_payload']) ? (is_array($trend['raw_payload']) ? $trend['raw_payload'] : (json_decode($trend['raw_payload'], true) ?: [])) : [];
            $retryCount = (int)($payload['retry_count'] ?? 0);

            // If max retries reached, escalate to human review queue
            if ($retryCount >= self::MAX_AUTO_RETRIES) {
                $escalatedReason = "Mandatory statutory facts unavailable after " . self::MAX_AUTO_RETRIES . " automated retries. Escalated to editorial review.";
                $payload['needs_human_review'] = true;
                $payload['review_reason'] = $escalatedReason;
                
                Database::execute(
                    "UPDATE trends SET raw_payload = :p, updated_at = NOW() WHERE id = :id",
                    ['p' => json_encode($payload), 'id' => $trendId]
                );
                Logger::warning("EnrichmentRetryService: Trend #{$trendId} escalated: {$escalatedReason}");
                $escalated++;
                continue;
            }

            // Increment retry count
            $payload['retry_count'] = $retryCount + 1;
            $payload['last_retried_at'] = date('Y-m-d H:i:s');
            Database::execute(
                "UPDATE trends SET raw_payload = :p WHERE id = :id",
                ['p' => json_encode($payload), 'id' => $trendId]
            );

            Logger::info("EnrichmentRetryService: Retrying Trend #{$trendId} ('{$trend['keyword']}') [Attempt #" . ($retryCount + 1) . "/" . self::MAX_AUTO_RETRIES . "]");

            try {
                // Attempt generation (force = true bypasses slot guard, but fact gates STILL apply)
                $result = $pipeline->generateFromTrend($trendId, true);
                if (!empty($result['success'])) {
                    Logger::info("EnrichmentRetryService: Trend #{$trendId} successfully enriched and generated as Article #{$result['article_id']}.");
                    $retried++;
                } else {
                    Logger::info("EnrichmentRetryService: Trend #{$trendId} still incomplete: " . ($result['error'] ?? 'Held'));
                }
            } catch (Throwable $e) {
                Logger::error("EnrichmentRetryService: Trend #{$trendId} error: " . $e->getMessage());
            }

            sleep(2); // Rate pacing
        }

        return [
            'processed' => count($held),
            'retried'   => $retried,
            'escalated' => $escalated
        ];
    }
}
