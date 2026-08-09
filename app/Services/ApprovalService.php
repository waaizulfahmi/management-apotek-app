<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ApprovalService
{
    protected $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    /**
     * Submit an approval request
     */
    public function createRequest(User $requester, string $module, string $actionType, string $description, ?array $payload = null): ApprovalRequest
    {
        $approval = ApprovalRequest::create([
            'requested_by' => $requester->id,
            'module' => $module,
            'action_type' => $actionType,
            'description' => $description,
            'payload' => $payload,
            'status' => 'PENDING',
        ]);

        $this->auditLogService->log(
            'SUBMIT_APPROVAL',
            $module,
            null,
            ['approval_id' => $approval->id, 'action_type' => $actionType],
            $requester->id,
            $requester->name
        );

        return $approval;
    }

    /**
     * Approve a request
     */
    public function approveRequest(ApprovalRequest $approval, User $approver, ?string $reason = null): bool
    {
        $approval->update([
            'approved_by' => $approver->id,
            'status' => 'APPROVED',
            'reason' => $reason ?? 'Approved by manager',
            'processed_at' => now(),
        ]);

        $this->auditLogService->log(
            'APPROVE',
            $approval->module,
            ['status' => 'PENDING'],
            ['status' => 'APPROVED', 'approval_id' => $approval->id],
            $approver->id,
            $approver->name
        );

        return true;
    }

    /**
     * Reject a request
     */
    public function rejectRequest(ApprovalRequest $approval, User $approver, string $reason): bool
    {
        $approval->update([
            'approved_by' => $approver->id,
            'status' => 'REJECTED',
            'reason' => $reason,
            'processed_at' => now(),
        ]);

        $this->auditLogService->log(
            'REJECT',
            $approval->module,
            ['status' => 'PENDING'],
            ['status' => 'REJECTED', 'approval_id' => $approval->id, 'reason' => $reason],
            $approver->id,
            $approver->name
        );

        return true;
    }
}
