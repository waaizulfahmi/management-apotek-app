<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\OutletService;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveOutlet
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $outlet = OutletService::getActiveOutlet();

        if ($outlet && ($outlet->status === 'INACTIVE' || !$outlet->is_active)) {
            // Block mutating requests when outlet is INACTIVE
            if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                if ($request->expectsJson() || $request->header('X-Inertia')) {
                    return back()->with('error', 'Outlet sedang tidak aktif dan tidak dapat digunakan untuk transaksi.');
                }
                return redirect()->back()->with('error', 'Outlet sedang tidak aktif dan tidak dapat digunakan untuk transaksi.');
            }
        }

        return $next($request);
    }
}
