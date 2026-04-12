<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\RequestType;
use App\Models\EmployeeRequest;
use App\Models\RequestComment;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RequestManagementController extends Controller
{
    // ── Reference generator ───────────────────────────────────────────────
    private function generateRef(): string
    {
        $year  = now()->year;
        $count = EmployeeRequest::whereYear('created_at', $year)->count() + 1;
        return 'REQ-' . $year . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
    }

    // ══════════════════════════════════════════════════════════════════════
    // REQUEST TYPES
    // ══════════════════════════════════════════════════════════════════════
    public function types()
    {
        return response()->json(['types' => RequestType::where('is_active', true)->orderBy('category')->orderBy('sort_order')->get()]);
    }

    public function allTypes()
    {
        return response()->json(['types' => RequestType::orderBy('category')->orderBy('sort_order')->get()]);
    }

    public function storeType(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:120',
            'code' => 'required|string|max:30|unique:request_types',
        ]);
        return response()->json(['type' => RequestType::create($request->all())], 201);
    }

    public function updateType(Request $request, $id)
    {
        $type = RequestType::findOrFail($id);
        $type->update($request->all());
        return response()->json(['type' => $type]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // STATS
    // ══════════════════════════════════════════════════════════════════════
    public function stats()
    {
        $safe = fn($fn) => rescue($fn, 0, false);
        $byCategory = rescue(function () {
            return DB::table('employee_requests')
                ->join('request_types','request_types.id','=','employee_requests.request_type_id')
                ->whereNotIn('employee_requests.status',['cancelled'])
                ->selectRaw('request_types.category, count(*) as total')
                ->groupBy('request_types.category')
                ->pluck('total','category');
        }, [], false);

        return response()->json([
            'pending'     => $safe(fn() => EmployeeRequest::whereIn('status',['pending','pending_manager'])->count()),
            'in_progress' => $safe(fn() => EmployeeRequest::where('status','in_progress')->count()),
            'completed'   => $safe(fn() => EmployeeRequest::where('status','completed')->count()),
            'overdue'     => $safe(fn() => EmployeeRequest::where('is_overdue', true)->whereNotIn('status',['completed','rejected','cancelled'])->count()),
            'by_category' => $byCategory,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // LIST
    // ══════════════════════════════════════════════════════════════════════
    public function index(Request $request)
    {
        $user   = auth()->user();
        $isMine = $request->scope === 'mine';

        // Role-based scope via raw DB (bypasses Spatie guard issues)
        $userRoles = rescue(fn() => DB::table('model_has_roles')
            ->join('roles','roles.id','=','model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)
            ->pluck('roles.name')->toArray(), [], false);

        $isHRAdmin = (bool) array_intersect($userRoles, ['super_admin','hr_manager','hr_staff']);
        $isMgr     = in_array('department_manager', $userRoles);

        // If not HR/admin and not explicitly requesting own, restrict to own
        $scopeToOwn = !$isHRAdmin && ($isMine || !$isMgr);

        $query = EmployeeRequest::with(['employee.department','requestType','assignedTo'])
            ->when($scopeToOwn && $user->employee, fn($q) => $q->where('employee_id', $user->employee->id))
            ->when($request->status,          fn($q) => $q->where('status', $request->status))
            ->when($request->category,        fn($q) => $q->whereHas('requestType', fn($rq) => $rq->where('category', $request->category)))
            ->when($request->request_type_id, fn($q) => $q->where('request_type_id', $request->request_type_id))
            ->when($request->overdue,         fn($q) => $q->where('is_overdue', true))
            ->when($request->search, fn($q) =>
                $q->where('reference','like',"%{$request->search}%")
                  ->orWhereHas('employee', fn($eq) =>
                      $eq->where('first_name','like',"%{$request->search}%")
                        ->orWhere('last_name','like',"%{$request->search}%")
                        ->orWhere('employee_code','like',"%{$request->search}%")
                  )
            )
            ->orderBy('created_at','desc');

        return response()->json($query->paginate(15));
    }

    // ══════════════════════════════════════════════════════════════════════
    // SHOW
    // ══════════════════════════════════════════════════════════════════════
    public function show($id)
    {
        $req = EmployeeRequest::with([
            'employee.department','employee.designation',
            'requestType','managerApprover','assignedTo','completedBy','rejectedBy',
            'comments.user',
        ])->findOrFail($id);
        return response()->json(['request' => $req]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // CREATE (employee submits)
    // ══════════════════════════════════════════════════════════════════════
    public function store(Request $request)
    {
        $request->validate([
            'request_type_id' => 'required|exists:request_types,id',
            'details'         => 'required|string|min:5',
            'required_by'     => 'nullable|date|after:today',
            'copies_needed'   => 'nullable|integer|min:1|max:20',
            'attachment'      => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
        ]);

        $employee = auth()->user()->employee;
        if (!$employee) {
            return response()->json(['message' => 'No employee record linked to your account.'], 422);
        }

        $type    = RequestType::findOrFail($request->request_type_id);
        $dueDate = now()->addDays($type->sla_days)->toDateString();

        // Enforce attachment requirement
        if ($type->requires_attachment && !$request->hasFile('attachment')) {
            return response()->json(['message' => "A supporting document is required for this request type."], 422);
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store(
                "requests/{$employee->id}", 'public'
            );
        }

        $req = EmployeeRequest::create([
            'reference'       => $this->generateRef(),
            'employee_id'     => $employee->id,
            'request_type_id' => $request->request_type_id,
            'status'          => $type->requires_manager_approval ? 'pending_manager' : 'pending',
            'details'         => $request->details,
            'required_by'     => $request->required_by,
            'copies_needed'   => $request->copies_needed ?? 1,
            'due_date'        => $dueDate,
            'attachment_path' => $attachmentPath,
        ]);

        return response()->json(['message' => 'Request submitted successfully.', 'request' => $req->load('requestType')], 201);
    }

    // ══════════════════════════════════════════════════════════════════════
    // MANAGER APPROVE
    // ══════════════════════════════════════════════════════════════════════
    public function managerApprove(Request $request, $id)
    {
        $req = EmployeeRequest::findOrFail($id);
        if ($req->status !== 'pending_manager') {
            return response()->json(['message' => 'Not awaiting manager approval.'], 422);
        }
        $userRoles = rescue(fn() => DB::table('model_has_roles')
            ->join('roles','roles.id','=','model_has_roles.role_id')
            ->where('model_has_roles.model_id', auth()->id())
            ->pluck('roles.name')->toArray(), [], false);

        $canApprove = (bool) array_intersect($userRoles, [
            'super_admin','hr_manager','hr_staff','department_manager'
        ]);
        if (!$canApprove) {
            return response()->json(['message' => 'Only managers can approve at this stage.'], 403);
        }
        $req->update([
            'status'              => 'pending',
            'manager_approved_by' => auth()->id(),
            'manager_approved_at' => now(),
            'manager_notes'       => $request->notes,
        ]);
        return response()->json(['message' => 'Approved — forwarded to HR.', 'request' => $req->fresh()]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // HR: TAKE / ASSIGN
    // ══════════════════════════════════════════════════════════════════════
    public function assign(Request $request, $id)
    {
        $req = EmployeeRequest::findOrFail($id);
        $req->update([
            'status'      => 'in_progress',
            'assigned_to' => $request->assigned_to ?? auth()->id(),
            'hr_notes'    => $request->hr_notes,
        ]);
        return response()->json(['message' => 'Request in progress.']);
    }

    // ══════════════════════════════════════════════════════════════════════
    // HR: COMPLETE
    // ══════════════════════════════════════════════════════════════════════
    public function complete(Request $request, $id)
    {
        $req = EmployeeRequest::findOrFail($id);

        $completionFile = $req->completion_file;
        if ($request->hasFile('completion_file')) {
            $request->validate(['completion_file' => 'file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240']);
            $completionFile = $request->file('completion_file')->store(
                "requests/completed/{$req->id}", 'public'
            );
        }

        $req->update([
            'status'           => 'completed',
            'completed_by'     => auth()->id(),
            'completed_at'     => now(),
            'completion_notes' => $request->completion_notes,
            'completion_file'  => $completionFile,
            'hr_notes'         => $request->hr_notes ?? $req->hr_notes,
        ]);

        // Auto-add a comment
        if ($request->completion_notes) {
            RequestComment::create([
                'request_id'  => $req->id,
                'user_id'     => auth()->id(),
                'comment'     => 'Request completed. ' . $request->completion_notes,
                'is_internal' => false,
            ]);
        }

        return response()->json(['message' => 'Request marked as completed.']);
    }

    // ══════════════════════════════════════════════════════════════════════
    // REJECT
    // ══════════════════════════════════════════════════════════════════════
    public function reject(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string|min:5']);
        $req = EmployeeRequest::findOrFail($id);

        if (in_array($req->status, ['completed','cancelled'])) {
            return response()->json(['message' => 'Cannot reject at this stage.'], 422);
        }
        $req->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->reason,
            'rejected_by'      => auth()->id(),
            'rejected_at'      => now(),
        ]);
        return response()->json(['message' => 'Request rejected.']);
    }

    // ══════════════════════════════════════════════════════════════════════
    // CANCEL (employee)
    // ══════════════════════════════════════════════════════════════════════
    public function cancel($id)
    {
        $req = EmployeeRequest::findOrFail($id);
        if (!in_array($req->status, ['pending','pending_manager'])) {
            return response()->json(['message' => 'Request cannot be cancelled at this stage.'], 422);
        }
        $req->update(['status' => 'cancelled']);
        return response()->json(['message' => 'Request cancelled.']);
    }

    // ══════════════════════════════════════════════════════════════════════
    // COMMENTS
    // ══════════════════════════════════════════════════════════════════════
    public function addComment(Request $request, $id)
    {
        $request->validate(['comment' => 'required|string|min:1']);
        $req = EmployeeRequest::findOrFail($id);

        $comment = RequestComment::create([
            'request_id'  => $req->id,
            'user_id'     => auth()->id(),
            'comment'     => $request->comment,
            'is_internal' => $request->is_internal ?? false,
        ]);

        return response()->json(['comment' => $comment->load('user')], 201);
    }

    // ══════════════════════════════════════════════════════════════════════
    // MARK OVERDUE (cron-friendly)
    // ══════════════════════════════════════════════════════════════════════
    public function markOverdue()
    {
        $count = EmployeeRequest::whereNotIn('status', ['completed','rejected','cancelled'])
            ->where('due_date', '<', now()->toDateString())
            ->update(['is_overdue' => true]);
        return response()->json(['message' => "{$count} requests marked as overdue."]);
    }
}
