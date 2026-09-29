<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\User;
use App\Services\OutletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OutletController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status');
        $search = $request->input('search');

        $query = Outlet::withTrashed()->orderBy('is_main', 'desc')->orderBy('id', 'asc');

        if ($status && $status !== 'ALL') {
            if ($status === 'TRASHED') {
                $query->onlyTrashed();
            } else {
                $query->where('status', $status)->whereNull('deleted_at');
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $outlets = $query->get();
        $users = User::select('id', 'name', 'username', 'email', 'role')->whereNull('deleted_at')->get();

        // Attach assigned user IDs to each outlet
        foreach ($outlets as $out) {
            $out->assigned_user_ids = DB::table('user_outlets')->where('outlet_id', $out->id)->pluck('user_id')->toArray();
        }

        return Inertia::render('Outlets/Index', [
            'outlets' => $outlets,
            'users' => $users,
            'activeOutletId' => OutletService::getActiveOutletId(),
            'filters' => [
                'status' => $status ?? 'ALL',
                'search' => $search ?? '',
            ]
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:50|unique:outlets,code',
            'name' => 'required|string|max:150',
            'legal_name' => 'nullable|string|max:200',
            'address' => 'nullable|string',
            'province' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:150',
            'pic_name' => 'nullable|string|max:150',
            'status' => 'required|in:ACTIVE,INACTIVE',
        ]);

        $isFirst = Outlet::count() === 0;

        $outlet = Outlet::create([
            'code' => strtoupper($request->code),
            'name' => $request->name,
            'legal_name' => $request->legal_name,
            'address' => $request->address,
            'province' => $request->province,
            'city' => $request->city,
            'district' => $request->district,
            'postal_code' => $request->postal_code,
            'phone' => $request->phone,
            'email' => $request->email,
            'pic_name' => $request->pic_name,
            'is_main' => $isFirst,
            'status' => $request->status,
            'is_active' => $request->status === 'ACTIVE',
        ]);

        if ($request->has('user_ids') && is_array($request->user_ids)) {
            $outlet->users()->sync($request->user_ids);
        }

        return redirect()->back()->with('success', "Outlet \"{$outlet->name}\" berhasil dibuat!");
    }

    public function update(Request $request, $id)
    {
        $outlet = Outlet::withTrashed()->findOrFail($id);

        $request->validate([
            'code' => 'required|string|max:50|unique:outlets,code,' . $id,
            'name' => 'required|string|max:150',
            'legal_name' => 'nullable|string|max:200',
            'address' => 'nullable|string',
            'province' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:150',
            'pic_name' => 'nullable|string|max:150',
            'status' => 'required|in:ACTIVE,INACTIVE',
        ]);

        $outlet->update([
            'code' => strtoupper($request->code),
            'name' => $request->name,
            'legal_name' => $request->legal_name,
            'address' => $request->address,
            'province' => $request->province,
            'city' => $request->city,
            'district' => $request->district,
            'postal_code' => $request->postal_code,
            'phone' => $request->phone,
            'email' => $request->email,
            'pic_name' => $request->pic_name,
            'status' => $request->status,
            'is_active' => $request->status === 'ACTIVE',
        ]);

        if ($request->has('user_ids') && is_array($request->user_ids)) {
            $outlet->users()->sync($request->user_ids);
        }

        return redirect()->back()->with('success', "Outlet \"{$outlet->name}\" berhasil diperbarui!");
    }

    public function destroy($id)
    {
        $outlet = Outlet::findOrFail($id);

        if ($outlet->is_main) {
            return redirect()->back()->with('error', 'Outlet Utama tidak dapat dihapus! Tentukan Outlet Utama lain terlebih dahulu.');
        }

        $outlet->delete();
        return redirect()->back()->with('success', "Outlet \"{$outlet->name}\" berhasil dinonaktifkan / disoft-delete.");
    }

    public function restore($id)
    {
        $outlet = Outlet::onlyTrashed()->findOrFail($id);
        $outlet->restore();
        return redirect()->back()->with('success', "Outlet \"{$outlet->name}\" berhasil dipulihkan!");
    }

    public function setMain($id)
    {
        DB::transaction(function () use ($id) {
            Outlet::query()->update(['is_main' => false]);
            $outlet = Outlet::findOrFail($id);
            $outlet->update(['is_main' => true, 'status' => 'ACTIVE', 'is_active' => true]);
        });

        return redirect()->back()->with('success', "Outlet Utama berhasil diubah.");
    }

    public function toggleStatus($id)
    {
        $outlet = Outlet::findOrFail($id);
        $newStatus = $outlet->status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
        $outlet->update([
            'status' => $newStatus,
            'is_active' => $newStatus === 'ACTIVE',
        ]);

        return redirect()->back()->with('success', "Status Outlet \"{$outlet->name}\" diubah menjadi {$newStatus}.");
    }

    public function switchOutlet(Request $request)
    {
        $request->validate([
            'outlet_id' => 'required|exists:outlets,id',
        ]);

        $success = OutletService::switchActiveOutlet((int)$request->outlet_id);

        if (!$success) {
            return redirect()->back()->with('error', 'Anda tidak memiliki hak akses ke outlet ini.');
        }

        $outlet = Outlet::find($request->outlet_id);
        return redirect()->back()->with('success', "Beralih ke outlet \"{$outlet->name}\".");
    }
}
