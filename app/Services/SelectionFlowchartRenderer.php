<?php
declare(strict_types=1);

namespace App\Services;

/**
 * SelectionFlowchartRenderer
 * Renders a clean, accessible visual flowchart for recruitment examination stages.
 * Part of Claude-engineered structural differentiation for RECRUITMENT intent articles.
 */
class SelectionFlowchartRenderer
{
    public static function render(array $stages = []): string
    {
        if (empty($stages)) {
            $stages = [
                ['stage' => 'Stage 1', 'title' => 'Preliminary Examination', 'desc' => 'Computer-Based Screening Test (Objective MCQ)'],
                ['stage' => 'Stage 2', 'title' => 'Main Written Examination', 'desc' => 'Core Subject & Descriptive Assessment'],
                ['stage' => 'Stage 3', 'title' => 'Document Verification & Medical', 'desc' => 'Original Certificate Verification & Fitness Standards'],
                ['stage' => 'Stage 4', 'title' => 'Final Selection List', 'desc' => 'Cadre & Department Allotment via Merit Rank']
            ];
        }

        $itemsHtml = '';
        $total = count($stages);
        foreach ($stages as $i => $s) {
            $num = $i + 1;
            $title = htmlspecialchars($s['title'] ?? "Stage {$num}", ENT_QUOTES, 'UTF-8');
            $desc = htmlspecialchars($s['desc'] ?? '', ENT_QUOTES, 'UTF-8');
            $isLast = ($i === $total - 1);

            $arrowHtml = !$isLast ? '<div class="stage-arrow" style="color: #94a3b8; font-size: 1.1rem; font-weight: 800; padding: 0 0.25rem;">➔</div>' : '';

            $itemsHtml .= <<<ITEM
<div class="flowchart-step" style="flex: 1; min-width: 150px; background: #ffffff; border: 1px solid #e2e8f0; border-top: 3px solid #1e3a8a; border-radius: 8px; padding: 0.85rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
    <div style="font-size: 0.7rem; font-weight: 800; color: #1e3a8a; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.25rem;">Stage {$num}</div>
    <div style="font-size: 0.875rem; font-weight: 700; color: #0f172a; margin-bottom: 0.35rem;">{$title}</div>
    <div style="font-size: 0.75rem; color: #64748b; line-height: 1.4;">{$desc}</div>
</div>
{$arrowHtml}
ITEM;
        }

        return <<<HTML
<div class="selection-flowchart-container" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; margin: 1.5rem 0;">
    <div style="font-size: 0.95rem; font-weight: 800; color: #0f172a; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1e3a8a" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
        Official Selection Stages &amp; Progression Roadmap
    </div>
    <div class="flowchart-stages" style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem;">
        {$itemsHtml}
    </div>
</div>
HTML;
    }
}
