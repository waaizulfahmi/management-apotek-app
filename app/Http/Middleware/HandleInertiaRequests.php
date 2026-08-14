<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $settings = \Illuminate\Support\Facades\Schema::hasTable('settings') 
            ? \Illuminate\Support\Facades\DB::table('settings')->pluck('value', 'key')->all()
            : [];

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'app_settings' => [
                'pharmacy_name' => $settings['pharmacy_name'] ?? 'Apotek Medika Sore',
                'pharmacy_logo' => $settings['pharmacy_logo'] ?? null,
                'pharmacy_address' => $settings['pharmacy_address'] ?? 'Jl. Raya Farmasi No. 10, Jakarta',
                'pharmacy_phone' => $settings['pharmacy_phone'] ?? '021-5551234',
                'pharmacist_name' => $settings['pharmacist_name'] ?? 'apt. Budi Santoso, S.Farm',
                'pharmacist_license' => $settings['pharmacist_license'] ?? 'SIPA/503/001/2026',
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
