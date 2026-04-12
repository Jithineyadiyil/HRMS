<?php
declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\{PerformanceCycle, PerformanceReview, Kpi, Employee};
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Manages performance cycles, reviews, self-assessments,
 * manager reviews, KPIs, and employee performance reports.
 */
class PerformanceController extends Controller
{
    // ── Role helper ───────────────────────────────────────────────────────
    private function userRoles(): array
    {
        return rescue(fn () => DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', auth()->id())
            ->pluck('roles.name')->toArray(), [], false);
    }

    private function isHRAdmin(): bool
    {
        return (bool) array_intersect(
            $this->userRoles(),
            ['super_admin', 'hr_manager', 'hr_staff']
        );
    }

    // ── Stats ─────────────────────────────────────────────────────────────
    public function stats(): JsonResponse
    {
        $safe = fn ($fn) => rescue($fn, 0, false);
        return response()->json([
            'total_cycles'      => $safe(fn () => PerformanceCycle::count()),
            'active_cycles'     => $safe(fn () => PerformanceCycle::where('status', 'active')->count()),
            'total_reviews'     => $safe(fn () => PerformanceReview::count()),
            'pending_self'      => $safe(fn () => PerformanceReview::where('status', 'pending')->count()),
            'pending_manager'   => $safe(fn () => PerformanceReview::where('status', 'self_submitted')->count()),
            'finalized'         => $safe(fn () => PerformanceReview::where('status', 'finalized')->count()),
            'avg_final_rating'  => $safe(fn () => round((float) PerformanceReview::whereNotNull('final_rating')->avg('final_rating'), 2)),
        ]);
    }

    // ── Cycles ────────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        // Return individual reviews when ?view=reviews
        if ($request->view === 'reviews') {
            $reviews = PerformanceReview::with(['employee.department', 'cycle', 'reviewer'])
                ->when($request->status,     fn ($q) => $q->where('status', $request->status))
                ->when($request->cycle_id,   fn ($q) => $q->where('cycle_id', $request->cycle_id))
                ->when($request->employee_id,fn ($q) => $q->where('employee_id', $request->employee_id))
                ->when(!$this->isHRAdmin() && auth()->user()->employee,
                    fn ($q) => $q->where('employee_id', auth()->user()->employee->id)
                )
                ->orderBy('created_at', 'desc')
                ->paginate((int) ($request->per_page ?? 20));
            return response()->json($reviews);
        }

        $cycles = PerformanceCycle::orderBy('start_date', 'desc')->paginate(12);
        $cycles->getCollection()->transform(function ($cycle) {
            $cycle->reviews_count   = DB::table('performance_reviews')->where('cycle_id', $cycle->id)->count();
            $cycle->finalized_count = DB::table('performance_reviews')->where('cycle_id', $cycle->id)->where('status', 'finalized')->count();
            $cycle->pending_count   = DB::table('performance_reviews')->where('cycle_id', $cycle->id)->where('status', 'pending')->count();
            return $cycle;
        });
        return response()->json($cycles);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'                       => 'required|string|max:150',
            'type'                       => 'required|in:annual,mid_year,quarterly,probation',
            'start_date'                 => 'required|date',
            'end_date'                   => 'required|date|after:start_date',
            'self_assessment_deadline'   => 'nullable|date',
            'manager_review_deadline'    => 'nullable|date',
        ]);
        $cycle = PerformanceCycle::create($request->all());
        return response()->json(['cycle' => $cycle], 201);
    }

    public function updateCycle(Request $request, int $id): JsonResponse
    {
        $cycle = PerformanceCycle::findOrFail($id);
        $request->validate([
            'name'   => 'sometimes|string|max:150',
            'status' => 'sometimes|in:draft,active,closed',
        ]);
        $cycle->update($request->all());
        return response()->json(['cycle' => $cycle->fresh()]);
    }

    public function show(int $id): JsonResponse
    {
        $cycle = PerformanceCycle::with([
            'reviews.employee.department', 'reviews.reviewer'
        ])->findOrFail($id);
        return response()->json(['cycle' => $cycle]);
    }

    /**
     * Initiate a cycle — bulk-creates PerformanceReview records
     * for all active employees (or specified ones).
     */
    public function initiate(Request $request, int $id): JsonResponse
    {
        if (!$this->isHRAdmin()) {
            return response()->json(['message' => 'Only HR can initiate a cycle.'], 403);
        }

        $cycle = PerformanceCycle::findOrFail($id);

        $employeeIds = $request->employee_ids
            ?? Employee::where('status', 'active')->pluck('id')->toArray();

        $created = 0;
        foreach ($employeeIds as $empId) {
            $exists = PerformanceReview::where('cycle_id', $id)
                ->where('employee_id', $empId)->exists();
            if (!$exists) {
                PerformanceReview::create([
                    'cycle_id'    => $id,
                    'employee_id' => $empId,
                    'status'      => 'pending',
                ]);
                $created++;
            }
        }

        $cycle->update(['status' => 'active']);
        return response()->json([
            'message' => "Cycle initiated. {$created} review records created.",
            'cycle'   => $cycle->fresh(),
        ]);
    }

    // ── Self Assessment ───────────────────────────────────────────────────
    public function selfAssessment(Request $request, int $reviewId): JsonResponse
    {
        $request->validate([
            'rating'     => 'required|numeric|min:1|max:5',
            'comments'   => 'required|string|min:10',
            'kpi_scores' => 'nullable|array',
        ]);

        $employee = auth()->user()->employee;
        if (!$employee) {
            return response()->json(['message' => 'No employee record linked.'], 422);
        }

        $review = PerformanceReview::where('id', $reviewId)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        if ($review->status === 'finalized') {
            return response()->json(['message' => 'Review is already finalized.'], 422);
        }

        $review->update([
            'self_rating'    => $request->rating,
            'self_comments'  => $request->comments,
            'self_kpi_scores'=> $request->kpi_scores,
            'status'         => 'self_submitted',
        ]);

        return response()->json(['message' => 'Self assessment submitted.', 'review' => $review->fresh()]);
    }

    // ── Manager Review ────────────────────────────────────────────────────
    public function managerReview(Request $request, int $reviewId): JsonResponse
    {
        if (!$this->isHRAdmin() && !in_array('department_manager', $this->userRoles())) {
            return response()->json(['message' => 'Only managers and HR can submit manager reviews.'], 403);
        }

        $request->validate([
            'rating'     => 'required|numeric|min:1|max:5',
            'comments'   => 'required|string|min:10',
            'kpi_scores' => 'nullable|array',
        ]);

        $review = PerformanceReview::findOrFail($reviewId);

        if (!in_array($review->status, ['self_submitted', 'pending'])) {
            return response()->json(['message' => 'Review is not ready for manager assessment.'], 422);
        }

        $review->update([
            'manager_rating'    => $request->rating,
            'manager_comments'  => $request->comments,
            'manager_kpi_scores'=> $request->kpi_scores,
            'reviewer_id'       => auth()->user()->employee?->id,
            'status'            => 'manager_reviewed',
        ]);

        return response()->json(['message' => 'Manager review submitted.', 'review' => $review->fresh(['employee', 'reviewer'])]);
    }

    // ── Finalize ──────────────────────────────────────────────────────────
    public function finalize(Request $request, int $reviewId): JsonResponse
    {
        if (!$this->isHRAdmin()) {
            return response()->json(['message' => 'Only HR can finalize reviews.'], 403);
        }

        $request->validate([
            'final_rating'     => 'required|numeric|min:1|max:5',
            'performance_band' => 'required|in:excellent,good,average,below_average,poor',
            'development_plan' => 'nullable|string',
            'hr_notes'         => 'nullable|string',
        ]);

        $review = PerformanceReview::findOrFail($reviewId);

        $review->update([
            'final_rating'     => $request->final_rating,
            'performance_band' => $request->performance_band,
            'development_plan' => $request->development_plan,
            'hr_notes'         => $request->hr_notes,
            'status'           => 'finalized',
        ]);

        return response()->json(['message' => 'Review finalized.', 'review' => $review->fresh(['employee', 'cycle'])]);
    }

    // ── KPIs ──────────────────────────────────────────────────────────────
    public function kpis(Request $request): JsonResponse
    {
        $kpis = Kpi::when($request->year,          fn ($q) => $q->where('year', $request->year))
                   ->when($request->department_id, fn ($q) => $q->where('department_id', $request->department_id))
                   ->when($request->employee_id,   fn ($q) => $q->where('employee_id', $request->employee_id))
                   ->get();
        return response()->json(['kpis' => $kpis]);
    }

    public function storeKpi(Request $request): JsonResponse
    {
        $request->validate([
            'title'    => 'required|string|max:200',
            'category' => 'required|string',
            'year'     => 'required|integer|min:2020|max:2099',
            'weight'   => 'nullable|numeric|min:0|max:100',
        ]);
        $kpi = Kpi::create($request->all());
        return response()->json(['kpi' => $kpi], 201);
    }

    public function updateKpi(Request $request, int $id): JsonResponse
    {
        $kpi = Kpi::findOrFail($id);
        $kpi->update($request->all());
        return response()->json(['kpi' => $kpi->fresh()]);
    }

    public function deleteKpi(int $id): JsonResponse
    {
        Kpi::findOrFail($id)->delete();
        return response()->json(['message' => 'KPI deleted.']);
    }

    // ── Employee report ───────────────────────────────────────────────────
    public function report(int $empId): JsonResponse
    {
        $reviews = PerformanceReview::with(['cycle', 'reviewer'])
            ->where('employee_id', $empId)
            ->orderBy('created_at', 'desc')
            ->get();

        $avg = $reviews->where('status', 'finalized')->avg('final_rating');

        return response()->json([
            'reviews'        => $reviews,
            'avg_rating'     => $avg ? round((float) $avg, 2) : null,
            'total_reviews'  => $reviews->count(),
            'finalized'      => $reviews->where('status', 'finalized')->count(),
        ]);
    }
}
