<?php

namespace App\Http\Controllers;

use App\Models\AccessLog;

class AccessLogController extends Controller
{
    public static function logSensitiveRead(string $entityType, ?int $entityId = null): void
    {
        $companyId = auth()->user()?->current_company_id ?? session('company_id');
        $userId = auth()->id();

        if (! $companyId) {
            return;
        }

        AccessLog::create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'subject_type' => $entityType,
            'subject_id' => $entityId,
            'action' => $entityId ? 'read' : 'export',
            'ip' => request()->ip(),
        ]);
    }
}
