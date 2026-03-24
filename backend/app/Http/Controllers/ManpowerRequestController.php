<?php
namespace App\Http\Controllers;

use App\Models\ManpowerRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ManpowerRequestController extends Controller
{
    /* ── List ─────────────────────────────────────────────────────── */
    public function index(Request $request)
    {
        $q = ManpowerRequest::with(['requester','department','approver'])
            ->when($request->scope === 'mine', fn($q) => $q->where('requested_by', Auth::id()))
            ->when($request->status,  fn($q, $v) => $q->where('status',  $v))
            ->when($request->urgency, fn($q, $v) => $q->where('urgency', $v))
            ->when($request->search,  fn($q, $v) =>
                $q->where(fn($q) =>
                    $q->where('position_title','like',"%$v%")
                      ->orWhere('reference','like',"%$v%")))
            ->latest();

        return response()->json($q->paginate($request->per_page ?? 15));
    }

    /* ── Show ─────────────────────────────────────────────────────── */
    public function show(ManpowerRequest $manpowerRequest)
    {
        $this->authorizeView($manpowerRequest);
        return response()->json([
            'manpower_request' => $manpowerRequest->load(['requester','department','approver'])
        ]);
    }

    /* ── Create (saves as draft) ──────────────────────────────────── */
    public function store(Request $request)
    {
        $data = $request->validate([
            'position_title'      => 'required|string|max:200',
            'department_id'       => 'required|exists:departments,id',
            'headcount'           => 'required|integer|min:1|max:100',
            'employment_type'     => 'required|in:full_time,part_time,contract,internship,freelance',
            'urgency'             => 'required|in:low,medium,high,critical',
            'reason'              => 'required|string',
            'expected_start_date' => 'nullable|date',
            'salary_min'          => 'nullable|integer|min:0',
            'salary_max'          => 'nullable|integer|min:0',
            'job_description'     => 'nullable|string',
            'requirements'        => 'nullable|string',
            'notes'               => 'nullable|string',
        ]);

        $data['requested_by'] = Auth::id();
        $data['status']       = 'draft';
        $mp = ManpowerRequest::create($data);

        return response()->json(['manpower_request' => $mp->load(['requester','department'])], 201);
    }

    /* ── Update (only draft/rejected) ────────────────────────────── */
    public function update(Request $request, ManpowerRequest $manpowerRequest)
    {
        abort_unless(in_array($manpowerRequest->status, ['draft','rejected']), 422, 'Cannot edit at this stage.');
        $this->authorizeOwner($manpowerRequest);

        $data = $request->validate([
            'position_title'      => 'sometimes|string|max:200',
            'department_id'       => 'sometimes|exists:departments,id',
            'headcount'           => 'sometimes|integer|min:1|max:100',
            'employment_type'     => 'sometimes|in:full_time,part_time,contract,internship,freelance',
            'urgency'             => 'sometimes|in:low,medium,high,critical',
            'reason'              => 'sometimes|string',
            'expected_start_date' => 'nullable|date',
            'salary_min'          => 'nullable|integer|min:0',
            'salary_max'          => 'nullable|integer|min:0',
            'job_description'     => 'nullable|string',
            'requirements'        => 'nullable|string',
            'notes'               => 'nullable|string',
        ]);

        if ($manpowerRequest->status === 'rejected') {
            $data['status'] = 'draft';
            $data['rejection_reason'] = null;
        }

        $manpowerRequest->update($data);
        return response()->json(['manpower_request' => $manpowerRequest->fresh(['requester','department'])]);
    }

    /* ── Submit draft → pending_hr ────────────────────────────────── */
    public function submit(ManpowerRequest $manpowerRequest)
    {
        $this->authorizeOwner($manpowerRequest);
        abort_unless($manpowerRequest->status === 'draft', 422, 'Only drafts can be submitted.');
        $manpowerRequest->update(['status' => 'pending_hr']);

        // TODO: send notification to HR team
        // Notification::send(User::role('hr_manager')->get(), new ManpowerRequestSubmitted($manpowerRequest));

        return response()->json(['manpower_request' => $manpowerRequest->fresh(['requester','department'])]);
    }

    /* ── HR Approve ───────────────────────────────────────────────── */
    public function approve(Request $request, ManpowerRequest $manpowerRequest)
    {
        $this->authorizeHR();
        abort_unless($manpowerRequest->status === 'pending_hr', 422, 'Only pending_hr requests can be approved.');

        $data = $request->validate([
            'hr_notes'           => 'nullable|string',
            'approved_headcount' => 'required|integer|min:1',
            'create_job_posting' => 'boolean',
        ]);

        DB::beginTransaction();
        try {
            $manpowerRequest->update([
                'status'             => 'approved',
                'approved_by'        => Auth::id(),
                'approved_at'        => now(),
                'hr_notes'           => $data['hr_notes'] ?? null,
                'approved_headcount' => $data['approved_headcount'],
            ]);

            $jobId = null;

            // Auto-create job posting if requested
            if (($data['create_job_posting'] ?? true) && class_exists('\App\Models\Job')) {
                $job = \App\Models\Job::create([
                    'title'               => $manpowerRequest->position_title,
                    'department_id'       => $manpowerRequest->department_id,
                    'employment_type'     => $manpowerRequest->employment_type,
                    'vacancies'           => $manpowerRequest->approved_headcount,
                    'salary_min'          => $manpowerRequest->salary_min,
                    'salary_max'          => $manpowerRequest->salary_max,
                    'description'         => $manpowerRequest->job_description,
                    'requirements'        => $manpowerRequest->requirements,
                    'status'              => 'open',
                    'source'              => 'manpower_request',
                    'manpower_request_id' => $manpowerRequest->id,
                    'created_by'          => Auth::id(),
                ]);
                $jobId = $job->id;
                $manpowerRequest->update([
                    'job_posting_created' => true,
                    'job_posting_id'      => $jobId,
                ]);
            }

            DB::commit();

            // TODO: notify the requesting manager
            // Notification::send($manpowerRequest->requester, new ManpowerRequestApproved($manpowerRequest));

            return response()->json([
                'manpower_request' => $manpowerRequest->fresh(['requester','department','approver']),
                'job_id'           => $jobId,
                'message'          => 'Approved' . ($jobId ? " — job posting #$jobId created." : "."),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Approval failed: ' . $e->getMessage()], 500);
        }
    }

    /* ── HR Reject ────────────────────────────────────────────────── */
    public function reject(Request $request, ManpowerRequest $manpowerRequest)
    {
        $this->authorizeHR();
        abort_unless($manpowerRequest->status === 'pending_hr', 422, 'Only pending_hr requests can be rejected.');

        $data = $request->validate(['reason' => 'required|string|min:5']);
        $manpowerRequest->update(['status' => 'rejected', 'rejection_reason' => $data['reason']]);

        // TODO: notify the requesting manager
        // Notification::send($manpowerRequest->requester, new ManpowerRequestRejected($manpowerRequest));

        return response()->json(['manpower_request' => $manpowerRequest->fresh(['requester','department'])]);
    }

    /* ── Stats ────────────────────────────────────────────────────── */
    public function stats()
    {
        return response()->json([
            'total'      => ManpowerRequest::count(),
            'draft'      => ManpowerRequest::where('status','draft')->count(),
            'pending_hr' => ManpowerRequest::where('status','pending_hr')->count(),
            'approved'   => ManpowerRequest::where('status','approved')->count(),
            'rejected'   => ManpowerRequest::where('status','rejected')->count(),
        ]);
    }

    /* ── Auth helpers ─────────────────────────────────────────────── */
    private function authorizeView(ManpowerRequest $mp): void
    {
        $u = Auth::user();
        abort_unless($u->hasAnyRole(['super_admin','hr_manager','hr_staff']) || $mp->requested_by === $u->id, 403);
    }
    private function authorizeOwner(ManpowerRequest $mp): void
    {
        $u = Auth::user();
        abort_unless($u->hasAnyRole(['super_admin','hr_manager','hr_staff']) || $mp->requested_by === $u->id, 403);
    }
    private function authorizeHR(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin','hr_manager','hr_staff']), 403, 'HR only.');
    }
}
