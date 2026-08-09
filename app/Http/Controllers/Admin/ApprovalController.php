<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Services\ApprovalService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ApprovalController extends Controller
{
    protected $approvalService;

    public function __construct(ApprovalService $approvalService)
    {
        $this->approvalService = $approvalService;
    }

    /**
     * List all approval requests
     */
    public function index(Request $request)
    {
        $query = ApprovalRequest::with(['requester', 'approver']);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->module) {
            $query->where('module', $request->module);
        }

        $approvals = $query->orderByRaw("FIELD(status, 'PENDING', 'APPROVED', 'REJECTED')")
                           ->orderBy('created_at', 'desc')
                           ->paginate(15)
                           ->withQueryString();

        return Inertia::render('Admin/Users/Approvals/Index', [
            'approvals' => $approvals,
            'filters' => $request->only(['status', 'module']),
        ]);
    }

    /**
     * Approve a request
     */
    public function approve(Request $request, $id)
    {
        $approval = ApprovalRequest::findOrFail($id);

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $this->approvalService->approveRequest($approval, auth()->user(), $request->reason);

        return redirect()->back()->with('success', 'Permintaan berhasil disetujui!');
    }

    /**
     * Reject a request
     */
    public function reject(Request $request, $id)
    {
        $approval = ApprovalRequest::findOrFail($id);

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $this->approvalService->rejectRequest($approval, auth()->user(), $request->reason);

        return redirect()->back()->with('success', 'Permintaan berhasil ditolak!');
    }
}
