<?php
declare(strict_types=1);

namespace App\Services;

enum ArticleIntent: string {
    case ADMIT_CARD      = 'admit_card';
    case RESULT_CUTOFF   = 'result_cutoff';
    case RECRUITMENT     = 'recruitment';
    case ANSWER_KEY      = 'answer_key';
    case COUNSELLING     = 'counselling';
    case SYLLABUS_CHANGE = 'syllabus_change';
    case CORRIGENDUM     = 'corrigendum';
}
