<?php
namespace App\Http\Controllers;

use App\Models\PerformanceCycle;
use App\Models\PerformanceFeedback;
use App\Models\PerformanceGoal;
use App\Models\PerformanceKpi;
use App\Models\PerformanceReview;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PerformanceController extends Controller
{
    /* ──────────────────────────────────────────────────────────
       STATS
    ────────────────────────────────────────────────────────── */
    public function stats()
    {
        $dist = [];
        foreach ([5,4,3,2,1] as $score) {
            $label = match($score) {
                5 => 'Outstanding (5)', 4 => 'Exceeds Exp (4)',
                3 => 'Meets Exp (3)',   2 => 'Below Avg (2)', 1 => 'Poor (1)'
            };
            $cnt = PerformanceReview::whereNotNull('final_score')
                ->whereBetween('final_score', [$score - 0.49, $score + 0.5])->count();
            $total = PerformanceReview::whereNotNull('final_score')->count();
            $dist[] = ['label' => $label, 'value' => $total ? round($cnt / $total * 100) : 0, 'color' => match($score) {
                5=>'#10b981',4=>'#3b82f6',3=>'#f59e0b',2=>'#f97316',default=>'#ef4444'
            }];
        }

        $topPerformers = PerformanceReview::with('employee.department')
            ->whereNotNull('final_score')->where('status','completed')
            ->orderByDesc('final_score')->limit(5)->get()
            ->map(fn($r) => [
                'name'       => $r->employee?->first_name . ' ' . $r->employee?->last_name,
                'department' => $r->employee?->department?->name,
                'score'      => $r->final_score,
            ]);

        return response()->json([
            'active_reviews'    => PerformanceReview::whereIn('status',['pending_self','pending_manager'])->count(),
            'completed_reviews' => PerformanceReview::where('status','completed')->count(),
            'pending_self'      => PerformanceReview::where('status','pending_self')->count(),
            'pending_manager'   => PerformanceReview::where('status','pending_manager')->count(),
            'active_goals'      => PerformanceGoal::whereIn('status',['not_started','in_progress'])->count(),
            'goals_achieved'    => PerformanceGoal::where('status','achieved')->count(),
            'total_reviews'     => PerformanceReview::count(),
            'total_goals'       => PerformanceGoal::count(),
            'total_kpis'        => PerformanceKpi::count(),
            'feedback_given'    => PerformanceFeedback::count(),
            'overdue_reviews'   => PerformanceReview::whereNotIn('status',['completed','cancelled'])
                ->whereDate('due_date','<',now())->count(),
            'avg_score'         => round(PerformanceReview::whereNotNull('final_score')->avg('final_score'), 1),
            'score_distribution'=> $dist,
            'top_performers'    => $topPerformers,
        ]);
    }

    /* ──────────────────────────────────────────────────────────
       CYCLES
    ────────────────────────────────────────────────────────── */
    public function cyclesIndex()
    {
        $cycles = PerformanceCycle::orderByDesc('created_at')->get();
        return response()->json(['data' => $cycles]);
    }

    public function cyclesStore(Request $request)
    {
        $data = $request->validate([
            'name'                       => 'required|string|max:255',
            'type'                       => 'required|in:annual,mid_year,quarterly,probation,pip',
            'review_period'              => 'nullable|string',
            'start_date'                 => 'required|date',
            'end_date'                   => 'nullable|date|after_or_equal:start_date',
            'self_assessment_deadline'   => 'nullable|date',
            'manager_review_deadline'    => 'nullable|date',
            'include_360'                => 'boolean',
            'description'                => 'nullable|string',
        ]);
        $data['created_by'] = Auth::id();
        $cycle = PerformanceCycle::create($data);
        return response()->json(['cycle' => $cycle], 201);
    }

    public function cyclesUpdate(Request $request, PerformanceCycle $cycle)
    {
        $data = $request->validate([
            'name'                       => 'sometimes|string|max:255',
            'type'                       => 'sometimes|in:annual,mid_year,quarterly,probation,pip',
            'review_period'              => 'nullable|string',
            'start_date'                 => 'sometimes|date',
            'end_date'                   => 'nullable|date',
            'self_assessment_deadline'   => 'nullable|date',
            'manager_review_deadline'    => 'nullable|date',
            'include_360'                => 'boolean',
            'description'                => 'nullable|string',
        ]);
        $cycle->update($data);
        return response()->json(['cycle' => $cycle->fresh()]);
    }

    public function cyclesActivate(PerformanceCycle $cycle)
    {
        abort_if($cycle->status !== 'draft', 422, 'Only draft cycles can be activated.');
        $cycle->update(['status' => 'active']);
        return response()->json(['cycle' => $cycle->fresh()]);
    }

    public function cyclesClose(PerformanceCycle $cycle)
    {
        abort_if($cycle->status !== 'active', 422, 'Only active cycles can be closed.');
        $cycle->update(['status' => 'closed']);
        return response()->json(['cycle' => $cycle->fresh()]);
    }

    /* ──────────────────────────────────────────────────────────
       REVIEWS
    ────────────────────────────────────────────────────────── */
    public function reviewsIndex(Request $request)
    {
        $q = PerformanceReview::with(['employee.department','reviewer','cycle'])
            ->orderByDesc('created_at');

        if ($request->search) {
            $s = $request->search;
            $q->whereHas('employee', fn($e) =>
                $e->where('first_name','like',"%$s%")->orWhere('last_name','like',"%$s%")
            );
        }
        if ($request->status)   $q->where('status', $request->status);
        if ($request->cycle_id) $q->where('cycle_id', $request->cycle_id);

        return response()->json($q->paginate($request->per_page ?? 15));
    }

    public function reviewsShow(PerformanceReview $review)
    {
        $review->load(['employee.department','reviewer','cycle','goals','kpis']);
        return response()->json(['review' => $review]);
    }

    public function reviewsStore(Request $request)
    {
        $data = $request->validate([
            'employee_id'   => 'required|exists:employees,id',
            'cycle_id'      => 'nullable|exists:performance_cycles,id',
            'reviewer_id'   => 'nullable|exists:users,id',
            'review_type'   => 'required|in:annual,mid_year,quarterly,probation,pip',
            'review_period' => 'nullable|string',
            'due_date'      => 'nullable|date',
            'notes'         => 'nullable|string',
        ]);
        $data['status']     = 'pending_self';
        $data['created_by'] = Auth::id();
        if (!isset($data['reviewer_id'])) {
            $data['reviewer_id'] = Auth::id();
        }
        $review = PerformanceReview::create($data);
        return response()->json(['review' => $review->load(['employee','cycle'])], 201);
    }

    public function reviewsUpdate(Request $request, PerformanceReview $review)
    {
        $data = $request->validate([
            'cycle_id'      => 'nullable|exists:performance_cycles,id',
            'reviewer_id'   => 'nullable|exists:users,id',
            'review_type'   => 'sometimes|in:annual,mid_year,quarterly,probation,pip',
            'review_period' => 'nullable|string',
            'due_date'      => 'nullable|date',
            'notes'         => 'nullable|string',
        ]);
        $review->update($data);
        return response()->json(['review' => $review->fresh()->load(['employee','cycle'])]);
    }

    /* ── Self Assessment ───────────────────────────────────── */
    public function selfAssessment(Request $request, PerformanceReview $review)
    {
        $data = $request->validate([
            'achievements'       => 'nullable|string',
            'challenges'         => 'nullable|string',
            'goals_next'         => 'nullable|string',
            'competency_scores'  => 'nullable|array',
            'overall_comment'    => 'nullable|string',
        ]);

        $selfScore = null;
        if (!empty($data['competency_scores'])) {
            $vals = array_values($data['competency_scores']);
            $selfScore = round(array_sum($vals) / count($vals), 1);
        }

        $review->update([
            'self_assessment'   => $data,
            'self_score'        => $selfScore,
            'status'            => 'pending_manager',
            'self_submitted_at' => now(),
        ]);

        return response()->json(['review' => $review->fresh()->load(['employee','cycle'])]);
    }

    /* ── Manager Evaluation ─────────────────────────────────── */
    public function managerEvaluation(Request $request, PerformanceReview $review)
    {
        $data = $request->validate([
            'competency_scores'  => 'nullable|array',
            'strengths'          => 'nullable|string',
            'improvements'       => 'nullable|string',
            'overall_comment'    => 'nullable|string',
            'recommended_action' => 'nullable|in:maintain,promote,increment,training,pip,terminate',
        ]);

        $managerScore = null;
        if (!empty($data['competency_scores'])) {
            $vals = array_values($data['competency_scores']);
            $managerScore = round(array_sum($vals) / count($vals), 1);
        }

        // Final score = weighted avg: 60% manager, 40% self
        $finalScore = null;
        if ($managerScore !== null) {
            $finalScore = $review->self_score
                ? round($managerScore * 0.6 + $review->self_score * 0.4, 1)
                : $managerScore;
        }

        $review->update([
            'manager_evaluation'     => $data,
            'manager_score'          => $managerScore,
            'final_score'            => $finalScore,
            'status'                 => 'completed',
            'manager_submitted_at'   => now(),
        ]);

        return response()->json(['review' => $review->fresh()->load(['employee','cycle'])]);
    }

    /* ──────────────────────────────────────────────────────────
       GOALS
    ────────────────────────────────────────────────────────── */
    public function goalsIndex(Request $request)
    {
        $q = PerformanceGoal::with('employee.department')->orderByDesc('created_at');
        if ($request->search)   { $s=$request->search; $q->whereHas('employee', fn($e) => $e->where('first_name','like',"%$s%")->orWhere('last_name','like',"%$s%"))->orWhere('title','like',"%$s%"); }
        if ($request->status)   $q->where('status',   $request->status);
        if ($request->category) $q->where('category', $request->category);
        return response()->json($q->paginate($request->per_page ?? 50));
    }

    public function goalsStore(Request $request)
    {
        $data = $request->validate([
            'employee_id'   => 'required|exists:employees,id',
            'review_id'     => 'nullable|exists:performance_reviews,id',
            'title'         => 'required|string|max:255',
            'description'   => 'nullable|string',
            'category'      => 'required|in:professional,learning,leadership,project,personal,okr',
            'priority'      => 'required|in:low,medium,high,critical',
            'status'        => 'required|in:not_started,in_progress,achieved,on_hold,cancelled',
            'target_value'  => 'nullable|numeric',
            'current_value' => 'nullable|numeric',
            'unit'          => 'nullable|string',
            'start_date'    => 'nullable|date',
            'due_date'      => 'nullable|date',
        ]);
        $data['created_by'] = Auth::id();
        if (($data['status'] ?? '') === 'achieved' && !isset($data['achieved_at'])) {
            $data['achieved_at'] = now()->toDateString();
        }
        $goal = PerformanceGoal::create($data);
        return response()->json(['goal' => $goal->load('employee')], 201);
    }

    public function goalsUpdate(Request $request, PerformanceGoal $goal)
    {
        $data = $request->validate([
            'title'         => 'sometimes|string|max:255',
            'description'   => 'nullable|string',
            'category'      => 'sometimes|in:professional,learning,leadership,project,personal,okr',
            'priority'      => 'sometimes|in:low,medium,high,critical',
            'status'        => 'sometimes|in:not_started,in_progress,achieved,on_hold,cancelled',
            'target_value'  => 'nullable|numeric',
            'current_value' => 'nullable|numeric',
            'unit'          => 'nullable|string',
            'start_date'    => 'nullable|date',
            'due_date'      => 'nullable|date',
        ]);
        if (($data['status'] ?? '') === 'achieved' && !$goal->achieved_at) {
            $data['achieved_at'] = now()->toDateString();
        }
        $goal->update($data);
        return response()->json(['goal' => $goal->fresh()->load('employee')]);
    }

    public function goalsProgressUpdate(Request $request, PerformanceGoal $goal)
    {
        $request->validate(['current_value' => 'required|numeric']);
        $goal->update(['current_value' => $request->current_value]);
        return response()->json(['goal' => $goal->fresh()]);
    }

    /* ──────────────────────────────────────────────────────────
       KPIs
    ────────────────────────────────────────────────────────── */
    public function kpisIndex(Request $request)
    {
        $q = PerformanceKpi::with('employee.department')->orderByDesc('created_at');
        if ($request->search) { $s=$request->search; $q->where('name','like',"%$s%")->orWhereHas('employee', fn($e) => $e->where('first_name','like',"%$s%")); }
        if ($request->status) $q->where('status', $request->status);
        return response()->json($q->paginate($request->per_page ?? 50));
    }

    public function kpisStore(Request $request)
    {
        $data = $request->validate([
            'employee_id'  => 'required|exists:employees,id',
            'review_id'    => 'nullable|exists:performance_reviews,id',
            'name'         => 'required|string|max:255',
            'description'  => 'nullable|string',
            'target'       => 'nullable|numeric',
            'actual'       => 'nullable|numeric',
            'unit'         => 'nullable|string',
            'period'       => 'nullable|string',
            'frequency'    => 'required|in:daily,weekly,monthly,quarterly,annual',
            'weight'       => 'nullable|numeric',
        ]);
        $data['created_by'] = Auth::id();
        // Auto-calculate status
        if (isset($data['target']) && isset($data['actual']) && $data['target'] > 0) {
            $pct = ($data['actual'] / $data['target']) * 100;
            $data['status'] = $pct >= 100 ? 'achieved' : ($pct >= 70 ? 'on_track' : ($pct >= 40 ? 'at_risk' : 'missed'));
        }
        $kpi = PerformanceKpi::create($data);
        return response()->json(['kpi' => $kpi->load('employee')], 201);
    }

    public function kpisUpdate(Request $request, PerformanceKpi $kpi)
    {
        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'target'      => 'nullable|numeric',
            'actual'      => 'nullable|numeric',
            'unit'        => 'nullable|string',
            'period'      => 'nullable|string',
            'frequency'   => 'sometimes|in:daily,weekly,monthly,quarterly,annual',
            'weight'      => 'nullable|numeric',
        ]);
        if (isset($data['target']) && isset($data['actual']) && $data['target'] > 0) {
            $pct = ($data['actual'] / $data['target']) * 100;
            $data['status'] = $pct >= 100 ? 'achieved' : ($pct >= 70 ? 'on_track' : ($pct >= 40 ? 'at_risk' : 'missed'));
        }
        $kpi->update($data);
        return response()->json(['kpi' => $kpi->fresh()->load('employee')]);
    }

    /* ──────────────────────────────────────────────────────────
       360° FEEDBACK
    ────────────────────────────────────────────────────────── */
    public function feedbackIndex(Request $request)
    {
        $q = PerformanceFeedback::with(['subject.department','reviewer','review'])
            ->orderByDesc('created_at');
        if ($request->search)       { $s=$request->search; $q->whereHas('subject', fn($e)=>$e->where('first_name','like',"%$s%")); }
        if ($request->relationship) $q->where('relationship', $request->relationship);
        return response()->json($q->paginate($request->per_page ?? 50));
    }

    public function feedbackStore(Request $request)
    {
        $data = $request->validate([
            'subject_employee_id' => 'required|exists:employees,id',
            'review_id'           => 'nullable|exists:performance_reviews,id',
            'relationship'        => 'required|in:self,manager,peer,report,client',
            'is_anonymous'        => 'boolean',
            'communication'       => 'nullable|integer|min:1|max:5',
            'teamwork'            => 'nullable|integer|min:1|max:5',
            'technical'           => 'nullable|integer|min:1|max:5',
            'leadership'          => 'nullable|integer|min:1|max:5',
            'initiative'          => 'nullable|integer|min:1|max:5',
            'strengths'           => 'nullable|string',
            'improvements'        => 'nullable|string',
            'overall_comment'     => 'nullable|string',
        ]);
        $data['reviewer_id']   = Auth::id();
        $data['submitted_at']  = now();
        $fb = PerformanceFeedback::create($data);

        // Recalculate 360 score on the review if linked
        if ($fb->review_id) {
            $avg = PerformanceFeedback::where('review_id', $fb->review_id)
                ->whereNotNull('communication')->get()
                ->map(fn($f) => $f->avg_score)->avg();
            PerformanceReview::find($fb->review_id)?->update(['feedback_360_score' => round($avg, 1)]);
        }

        return response()->json(['feedback' => $fb->load(['subject','reviewer'])], 201);
    }
}
