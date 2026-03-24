<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\{PerformanceCycle, PerformanceReview, Kpi};
use Illuminate\Http\Request;

class PerformanceController extends Controller {
    public function index(\Illuminate\Http\Request $request) {
        // Return cycles list (default) or individual reviews when ?view=reviews
        if ($request->view === 'reviews') {
            $reviews = PerformanceReview::with(['employee.department', 'cycle'])
                ->when($request->status, fn ($q) => $q->where('status', $request->status))
                ->orderBy('created_at', 'desc')
                ->paginate((int) ($request->per_page ?? 15));
            return response()->json($reviews);
        }
        return response()->json(PerformanceCycle::withCount('reviews')->orderBy('start_date','desc')->paginate(10));
    }
    public function store(Request $request) {
        $request->validate(['name'=>'required','type'=>'required','start_date'=>'required|date','end_date'=>'required|date|after:start_date']);
        $cycle = PerformanceCycle::create($request->all());
        return response()->json(['cycle' => $cycle], 201);
    }
    public function show($id) {
        return response()->json(['cycle' => PerformanceCycle::with('reviews.employee')->findOrFail($id)]);
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
