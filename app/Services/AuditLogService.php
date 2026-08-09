<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    /**
     * Record an audit log entry
     */
    public function log(string $action, string $module, ?array $dataBefore = null, ?array $dataAfter = null, ?int $userId = null, ?string $userName = null): AuditLog
    {
        $currentUser = Auth::user();

        return AuditLog::create([
            'user_id' => $userId ?? ($currentUser ? $currentUser->id : null),
            'action' => strtoupper($action),
            'module' => $module,
            'ip_address' => Request::ip() ?? '127.0.0.1',
            'user_agent' => Request::userAgent() ?? '',
            'old_values' => $dataBefore,
            'new_values' => $dataAfter,
        ]);
    }
}
