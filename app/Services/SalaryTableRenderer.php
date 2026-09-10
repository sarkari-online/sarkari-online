<?php
declare(strict_types=1);

namespace App\Services;

/**
 * SalaryTableRenderer
 *
 * Renders an authoritative, clean HTML salary structure table for full-form entries
 * using verified data from full_form_entity_facts.
 *
 * Enforces:
 * - Minimum 2 populated rows or returns null (fail-closed)
 * - Returns null if confidence is UNAVAILABLE
 * - Standard Indian currency formatting with ₹ and commas
 * - Embedded CTA to Sarkari.online's 7th Pay Commission Salary Calculator
 */
class SalaryTableRenderer
{
    /**
     * Render HTML salary table or null if data is insufficient.
     */
    public static function render(array $facts): ?string
    {
        $confidence = $facts['confidence'] ?? 'UNAVAILABLE';
        if ($confidence === 'UNAVAILABLE') {
            return null;
        }

        $rows = [];

        // 1. Pay Level / Scale
        if (!empty($facts['pay_level_7cpc'])) {
            $rows[] = [
                'label' => 'Pay Level / Scale',
                'value' => htmlspecialchars($facts['pay_level_7cpc'], ENT_QUOTES, 'UTF-8')
            ];
        }

        // 2. Basic Pay Scale Range
        $minBasic = !empty($facts['basic_pay_min']) ? (int)$facts['basic_pay_min'] : null;
        $maxBasic = !empty($facts['basic_pay_max']) ? (int)$facts['basic_pay_max'] : null;

        if ($minBasic !== null && $maxBasic !== null) {
            if ($minBasic === $maxBasic) {
                $basicText = '₹' . number_format($minBasic);
            } else {
                $basicText = '₹' . number_format($minBasic) . ' – ₹' . number_format($maxBasic);
            }
            $rows[] = [
                'label' => 'Basic Pay Scale (7th CPC)',
                'value' => $basicText
            ];
        }

        // 3. Gross / In-Hand Monthly Range
        $minGross = !empty($facts['gross_salary_min']) ? (int)$facts['gross_salary_min'] : null;
        $maxGross = !empty($facts['gross_salary_max']) ? (int)$facts['gross_salary_max'] : null;

        if ($minGross !== null && $maxGross !== null) {
            if ($minGross === $maxGross) {
                $grossText = '₹' . number_format($minGross) . ' / month (approx)';
            } else {
                $grossText = '₹' . number_format($minGross) . ' – ₹' . number_format($maxGross) . ' / month (approx)';
            }
            $rows[] = [
                'label' => 'Estimated Monthly Gross / In-Hand',
                'value' => $grossText
            ];
        }

        // 4. Allowances & Perks
        if (!empty($facts['allowances_summary'])) {
            $rows[] = [
                'label' => 'Applicable Allowances & Perks',
                'value' => htmlspecialchars($facts['allowances_summary'], ENT_QUOTES, 'UTF-8')
            ];
        }

        // Governance rule: If fewer than 2 populated rows, do not render a partial stub table
        if (count($rows) < 2) {
            return null;
        }

        $calcUrl = function_exists('url') ? url('tools/7th-pay-commission-salary-calculator/') : '/tools/7th-pay-commission-salary-calculator/';

        $tbody = '';
        foreach ($rows as $r) {
            $tbody .= "    <tr>\n"
                    . "      <td style=\"padding: 10px 14px; font-weight: 700; color: #1e293b; background: #f8fafc; border-bottom: 1px solid #e2e8f0; width: 35%;\">{$r['label']}</td>\n"
                    . "      <td style=\"padding: 10px 14px; color: #334155; border-bottom: 1px solid #e2e8f0;\">{$r['value']}</td>\n"
                    . "    </tr>\n";
        }

        $html = "<div class=\"salary-table-wrapper\" style=\"margin: 1.5rem 0; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);\">\n"
              . "  <div style=\"overflow-x: auto;\">\n"
              . "    <table style=\"width: 100%; border-collapse: collapse; font-size: 0.925rem; text-align: left;\">\n"
              . "      <thead>\n"
              . "        <tr style=\"background: #1e3a8a; color: #ffffff;\">\n"
              . "          <th style=\"padding: 11px 14px; font-weight: 700; border: none;\">Compensation Component</th>\n"
              . "          <th style=\"padding: 11px 14px; font-weight: 700; border: none;\">Entitlement / Amount</th>\n"
              . "        </tr>\n"
              . "      </thead>\n"
              . "      <tbody>\n"
              . $tbody
              . "      </tbody>\n"
              . "    </table>\n"
              . "  </div>\n"
              . "  <div style=\"padding: 0.85rem 1.15rem; background: #f0fdf4; border-top: 1px solid #bbf7d0; font-size: 0.875rem; color: #166534; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;\">\n"
              . "    <span>Calculate your exact in-hand salary as per HRA city classification (X, Y, Z) &amp; prevailing DA:</span>\n"
              . "    <a href=\"{$calcUrl}\" style=\"color: #15803d; font-weight: 700; text-decoration: underline; text-underline-offset: 3px; display: inline-flex; align-items: center; gap: 4px;\">\n"
              . "      <span>7th Pay Salary Calculator</span> &rarr;\n"
              . "    </a>\n"
              . "  </div>\n"
              . "</div>\n";

        return $html;
    }
}
