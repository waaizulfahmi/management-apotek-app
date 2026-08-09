<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function index()
    {
        $auditLogs = DB::table('audit_logs')
            ->leftJoin('users', 'audit_logs.user_id', '=', 'users.id')
            ->select('audit_logs.*', 'users.name as user_name')
            ->orderBy('audit_logs.id', 'desc')
            ->paginate(15);

        $settings = DB::table('settings')->pluck('value', 'key')->all();

        return Inertia::render('Settings/Index', [
            'auditLogs' => $auditLogs,
            'settings' => $settings,
        ]);
    }

    public function updateSettings(Request $request)
    {
        foreach ($request->except('_token') as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
        }

        return redirect()->route('settings.index')->with('success', 'Pengaturan apotek berhasil disimpan!');
    }
}
