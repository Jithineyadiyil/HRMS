<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\{PerformanceCycle, PerformanceReview, Kpi};
use Illuminate\Http\Request;

class PerformanceController extends Controller {

    /** GET /performance/stats — dashboard numbers */
    public function stats() {
        $totalCycles   = PerformanceCycle::count();
        $activeCycles  = PerformanceCycle::where('status', 'active')->count();
        $totalReviews  = PerformanceReview::count();
        $pendingReviews= PerformanceReview::where('status', 'pending')->count();
        $completedReviews = PerformanceReview::where('status', 'finalized')->count();

        $avgRating = PerformanceReview::whereNotNull('final_rating')->avg('final_rating');

        $byStatus  = PerformanceReview::selectRaw('status, count(*) as count')
            ->groupBy('status')->pluck('count', 'status');

        $byBand    = PerformanceReview::selectRaw('performance_band, count(*) as count')
            ->whereNotNull('performance_band')->groupBy('performance_band')
            ->pluck('count', 'performance_band');

        $recentReviews = PerformanceReview::with(['employee', 'cycle'])
            ->latest()->limit(5)->get()->map(fn($r) => [
                'id'           => $r->id,
                'employee'     => $r->employee?->full_name ?? '—',
                'cycle'        => $r->cycle?->name ?? '—',
                'status'       => $r->status,
                'final_rating' => $r->final_rating,
                'band'         => $r->performance_band,
            ]);

        return response()->json([
            'total_cycles'     => $totalCycles,
            'active_cycles'    => $activeCycles,
            'total_reviews'    => $totalReviews,
            'pending_reviews'  => $pendingReviews,
            'completed_reviews'=> $completedReviews,
            'avg_rating'       => $avgRating ? round($avgRating, 2) : null,
            'by_status'        => $byStatus,
            'by_band'          => $byBand,
            'recent_reviews'   => $recentReviews,
        ]);
    }

    /** GET /performance/reviews — paginated reviews (not cycles) */
    public function reviews(Request $request) {
        $query = PerformanceReview::with(['employee.department', 'employee.designation', 'cycle', 'reviewer'])
            ->when($request->cycle_id,  fn($q) => $q->where('cycle_id',   $request->cycle_id))
            ->when($request->status,    fn($q) => $q->where('status',     $request->status))
            ->when($request->employee_id, fn($q) => $q->where('employee_id', $request->employee_id))
            ->orderBy('created_at', 'desc');
        return response()->json($query->paginate((int)($request->per_page ?? 20)));
    }

    /** GET /performance/cycles — paginated cycles */
    public function index() {
        return response()->json(PerformanceCycle::withCount('reviews')->orderBy('start_date','desc')->paginate(10));
    }

    public function store(Request $request) {
        $request->validate([
            'name'       => 'required|string|max:100',
            'type'       => 'required|in:annual,semi_annual,quarterly',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
            'self_assessment_deadline' => 'nullable|date',
            'manager_review_deadline'  => 'nullable|date',
            'status'     => 'sometimes|in:draft,active,completed,archived',
        ]);
        $cycle = PerformanceCycle::create($request->all());
        return response()->json(['cycle' => $cycle], 201);
    }
    public function show($id) {
        return response()->json(['cycle' => PerformanceCycle::with('reviews.employee')->findOrFail($id)]);
    }
    public function showReview($id) {
        $review = PerformanceReview::with(['employee.department','employee.designation','cycle','reviewer'])->findOrFail($id);
        return response()->json(['review' => $review]);
    }

    public function selfAssessment(Request $request, $id) {
        $employee = auth()->user()->employee;
        $review   = PerformanceReview::firstOrCreate(['cycle_id'=>$id,'employee_id'=>$employee->id]);
        $review->update(['self_rating'=>$request->rating,'self_comments'=>$request->comments,'self_kpi_scores'=>$request->kpi_scores,'status'=>'self_submitted']);
        return response()->json(['message' => 'Self assessment submitted', 'review' => $review]);
    }
    public function managerReview(Request $request, $id) {
        $review = PerformanceReview::where('cycle_id', $id)->where('employee_id', $request->employee_id)->firstOrFail();
        $review->update(['manager_rating'=>$request->rating,'manager_comments'=>$request->comments,'manager_kpi_scores'=>$request->kpi_scores,'reviewer_id'=>auth()->user()->employee->id,'status'=>'manager_reviewed']);
        return response()->json(['message' => 'Manager review submitted', 'review' => $review]);
    }
    public function finalize(Request $request, $id) {
        $review = PerformanceReview::where('cycle_id',$id)->where('employee_id',$request->employee_id)->firstOrFail();
        $review->update(['final_rating'=>$request->final_rating,'performance_band'=>$request->performance_band,'development_plan'=>$request->development_plan,'hr_notes'=>$request->hr_notes,'status'=>'finalized']);
        return response()->json(['review' => $review]);
    }
    public function kpis(Request $request) {
        return response()->json(Kpi::where('year', $request->year ?? now()->year)->when($request->department_id, fn($q) => $q->where('department_id', $request->department_id))->get());
    }
    public function storeKpi(Request $request) {
        $request->validate(['title'=>'required','category'=>'required','year'=>'required|integer']);
        return response()->json(['kpi' => Kpi::create($request->all())], 201);
    }
    public function updateKpi(Request $request, $id) {
        $kpi = Kpi::findOrFail($id); $kpi->update($request->all());
        return response()->json(['kpi' => $kpi]);
    }
    public function report($empId) {
        $reviews = PerformanceReview::with('cycle')->where('employee_id',$empId)->orderBy('created_at','desc')->get();
        return response()->json(['reviews' => $reviews]);
    }
}
