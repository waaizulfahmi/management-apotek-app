<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\ImageService;
use Inertia\Inertia;
use Exception;

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
        $request->validate([
            'pharmacy_name' => 'nullable|string|max:255',
            'pharmacy_address' => 'nullable|string',
            'pharmacy_phone' => 'nullable|string',
            'pharmacist_name' => 'nullable|string',
            'pharmacist_license' => 'nullable|string',
            'expired_warning_days' => 'nullable|integer',
            'pharmacy_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
        ]);

        try {
            // Handle logo file upload if provided
            if ($request->hasFile('pharmacy_logo')) {
                // Delete old logo file if exists
                $oldLogo = DB::table('settings')->where('key', 'pharmacy_logo')->value('value');
                if ($oldLogo && file_exists(public_path($oldLogo))) {
                    ImageService::deleteFile(public_path($oldLogo));
                }

                $filename = ImageService::uploadAsWebp(
                    $request->file('pharmacy_logo'),
                    public_path('uploads/logo'),
                    85,
                    'logo'
                );
                $logoUrl = '/uploads/logo/' . $filename;

                DB::table('settings')->updateOrInsert(
                    ['key' => 'pharmacy_logo'],
                    ['value' => $logoUrl, 'updated_at' => now()]
                );
            }

            foreach ($request->except(['_token', 'pharmacy_logo']) as $key => $value) {
                if ($value !== null) {
                    DB::table('settings')->updateOrInsert(
                        ['key' => $key],
                        ['value' => $value, 'updated_at' => now()]
                    );
                }
            }

            // Audit Log
            DB::table('audit_logs')->insert([
                'user_id' => auth()->id(),
                'action' => 'UPDATE_SETTINGS',
                'module' => 'Settings',
                'record_id' => 'SystemSettings',
                'new_values' => json_encode($request->except(['_token', 'pharmacy_logo'])),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return redirect()->route('settings.index')->with('success', 'Pengaturan Apotek & Logo berhasil disimpan!');
        } catch (Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function resetLogo()
    {
        DB::table('settings')->where('key', 'pharmacy_logo')->delete();
        return redirect()->route('settings.index')->with('success', 'Logo apotek berhasil direset ke logo standar!');
    }
}
