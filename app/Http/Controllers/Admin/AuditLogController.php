<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditLogController extends Controller
{
    /**
     * Audit Log list page with filters
     */
    public function index(Request $request)
    {
        $query = AuditLog::with('user');

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('user_name', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%")
                  ->orWhere('module', 'like', "%{$search}%");
            });
        }

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->module) {
            $query->where('module', $request->module);
        }

        if ($request->action) {
            $query->where('action', $request->action);
        }

        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        $modules = AuditLog::distinct()->pluck('module');
        $actions = AuditLog::distinct()->pluck('action');

        return Inertia::render('Admin/Users/AuditLogs/Index', [
            'logs' => $logs,
            'modules' => $modules,
            'actions' => $actions,
            'filters' => $request->only(['search', 'user_id', 'module', 'action', 'date_from', 'date_to']),
        ]);
    }
}
