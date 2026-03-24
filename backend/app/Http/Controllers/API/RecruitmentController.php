<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\{JobPosting, JobApplication, Interview};
use App\Services\RecruitmentService;
use Illuminate\Http\Request;

class RecruitmentController extends Controller {
    protected $service;
    public function __construct(RecruitmentService $service) { $this->service = $service; }

    public function stats() {
        $totalJobs      = JobPosting::count();
        $openJobs       = JobPosting::where('status', 'open')->count();
        $totalApps      = JobApplication::count();
        $newThisWeek    = JobApplication::where('created_at', '>=', now()->startOfWeek())->count();
        $inInterview    = JobApplication::where('stage', 'interview')->count();
        $offersOut      = JobApplication::where('stage', 'offer')->count();
        $hiredThisMonth = JobApplication::where('stage', 'hired')
            ->whereMonth('updated_at', now()->month)->whereYear('updated_at', now()->year)->count();
        $rejectedTotal  = JobApplication::where('stage', 'rejected')->count();

        $byStage = JobApplication::selectRaw('stage, count(*) as count')
            ->groupBy('stage')->pluck('count', 'stage');

        $recentApps = JobApplication::with('jobPosting')
            ->latest()->limit(5)->get()->map(fn($a) => [
                'id'             => $a->id,
                'applicant_name' => $a->applicant_name,
                'job_title'      => $a->jobPosting?->title ?? '—',
                'stage'          => $a->stage,
                'applied_at'     => $a->created_at->toDateString(),
            ]);

        $byStatus = JobPosting::selectRaw('status, count(*) as count')
            ->groupBy('status')->pluck('count', 'status');

        return response()->json([
            'total_jobs'       => $totalJobs,
            'open_jobs'        => $openJobs,
            'by_status'        => $byStatus,
            'total_applicants' => $totalApps,
            'new_this_week'    => $newThisWeek,
            'in_interview'     => $inInterview,
            'offers_out'       => $offersOut,
            'hired_this_month' => $hiredThisMonth,
            'rejected_total'   => $rejectedTotal,
            'by_stage'         => $byStage,
            'recent_applicants'=> $recentApps,
        ]);
    }

    public function jobs(Request $request) {
        $jobs = JobPosting::with(['department','designation'])
            ->withCount('applications') // exposes applications_count
            ->when($request->status,        fn($q) => $q->where('status', $request->status))
            ->when($request->department_id, fn($q) => $q->where('department_id', $request->department_id))
            ->when($request->search,        fn($q) => $q->where('title', 'like', "%{$request->search}%"))
            ->orderBy('created_at', 'desc')
            ->paginate((int) ($request->per_page ?? 15));
        return response()->json($jobs);
    }

    public function publicJobs() {
        $jobs = JobPosting::with(['department'])->where('status','open')->where(function($q){ $q->whereNull('closing_date')->orWhere('closing_date','>=',now()); })->get();
        return response()->json(['jobs' => $jobs]);
    }

    public function storeJob(Request $request) {
        $request->validate([
            'title'           => 'required|string|max:150',
            'employment_type' => 'required|in:full_time,part_time,contract,intern',
            'description'     => 'required|string',
            'status'          => 'sometimes|in:draft,open,on_hold,closed',
            'department_id'   => 'nullable|exists:departments,id',
            'designation_id'  => 'nullable|exists:designations,id',
            'vacancies'       => 'nullable|integer|min:1',
            'salary_min'      => 'nullable|numeric|min:0',
            'salary_max'      => 'nullable|numeric|min:0',
            'closing_date'    => 'nullable|date|after:today',
        ]);
        $data = $request->all();
        $data['created_by']   = auth()->id();
        $data['department_id']  = $data['department_id']  ?? null;
        $data['designation_id'] = $data['designation_id'] ?? null;
        $data['salary_min']     = $data['salary_min']     ?: null;
        $data['salary_max']     = $data['salary_max']     ?: null;
        $data['closing_date']   = $data['closing_date']   ?: null;
        $job = JobPosting::create($data);
        return response()->json(['job' => $job->load('department', 'designation')], 201);
    }

    public function updateJob(Request $request, $id) {
        $job = JobPosting::findOrFail($id);
        $request->validate([
            'title'           => 'sometimes|required|string|max:150',
            'employment_type' => 'sometimes|required|in:full_time,part_time,contract,intern',
            'status'          => 'sometimes|required|in:draft,open,on_hold,closed',
            'department_id'   => 'nullable|exists:departments,id',
            'designation_id'  => 'nullable|exists:designations,id',
            'vacancies'       => 'nullable|integer|min:1',
            'salary_min'      => 'nullable|numeric|min:0',
            'salary_max'      => 'nullable|numeric|min:0',
            'closing_date'    => 'nullable|date',
        ]);
        $data = $request->all();
        if (array_key_exists('department_id',  $data)) $data['department_id']  = $data['department_id']  ?: null;
        if (array_key_exists('designation_id', $data)) $data['designation_id'] = $data['designation_id'] ?: null;
        if (array_key_exists('salary_min',     $data)) $data['salary_min']     = $data['salary_min']     ?: null;
        if (array_key_exists('salary_max',     $data)) $data['salary_max']     = $data['salary_max']     ?: null;
        if (array_key_exists('closing_date',   $data)) $data['closing_date']   = $data['closing_date']   ?: null;
        $job->update($data);
        return response()->json(['job' => $job->fresh()->load(['department', 'designation'])->loadCount('applications')]);
    }

    public function deleteJob($id) {
        JobPosting::findOrFail($id)->delete();
        return response()->json(['message' => 'Job posting deleted']);
    }

    public function apply(Request $request, $jobId) {
        $request->validate([
            'applicant_name'    => 'required|string|max:150',
            'applicant_email'   => 'required|email',
            'applicant_phone'   => 'nullable|string|max:25',
            'cv_path'           => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'cover_letter_text' => 'nullable|string',
            'expected_salary'   => 'nullable|numeric|min:0',
            'available_from'    => 'nullable|date',
        ]);

        // Allow adding applicants to any job (not just open) when called by HR internally
        $job = JobPosting::findOrFail($jobId);

        // Prevent duplicate application for same email on same job
        $existing = JobApplication::where('job_posting_id', $jobId)
            ->where('applicant_email', $request->applicant_email)
            ->first();
        if ($existing) {
            return response()->json([
                'message' => 'An application from this email already exists for this position.',
            ], 422);
        }

        $cvPath = $request->hasFile('cv_path')
            ? $request->file('cv_path')->store("recruitment/cvs/{$jobId}")
            : null;

        $application = JobApplication::create([
            'job_posting_id'    => $jobId,
            'applicant_name'    => $request->applicant_name,
            'applicant_email'   => $request->applicant_email,
            'applicant_phone'   => $request->applicant_phone,
            'cover_letter_text' => $request->cover_letter_text,
            'expected_salary'   => $request->expected_salary ?: null,
            'available_from'    => $request->available_from  ?: null,
            'cv_path'           => $cvPath,
            'stage'             => 'applied',
        ]);

        return response()->json(['message' => 'Applicant added successfully.', 'application' => $application], 201);
    }

    public function publicApply(Request $request, $jobId) { return $this->apply($request, $jobId); }

    public function applications(Request $request) {
        $apps = JobApplication::with(['jobPosting.department'])
            ->when($request->job_posting_id, fn($q) => $q->where('job_posting_id', $request->job_posting_id))
            ->when($request->stage, fn($q) => $q->where('stage', $request->stage))
            ->orderBy('created_at','desc')->paginate(20);
        return response()->json($apps);
    }

    public function showApplication($id) {
        $app = JobApplication::with(['jobPosting','interviews'])->findOrFail($id);
        return response()->json(['application' => $app]);
    }

    public function updateStage(Request $request, $id) {
        $request->validate(['stage'=>'required|in:applied,screening,interview,offer,hired,rejected']);
        $app = JobApplication::findOrFail($id);
        $app->update(['stage' => $request->stage, 'hr_notes' => $request->hr_notes]);
        return response()->json(['application' => $app]);
    }

    public function scheduleInterview(Request $request) {
        $request->validate(['application_id'=>'required|exists:job_applications,id','round'=>'required','scheduled_at'=>'required|date']);
        $interview = Interview::create($request->all());
        $this->service->sendInterviewInvite($interview);
        return response()->json(['interview' => $interview], 201);
    }

    public function updateInterview(Request $request, $id) {
        $interview = Interview::findOrFail($id);
        $interview->update($request->all());
        return response()->json(['interview' => $interview]);
    }

    public function sendOffer(Request $request, $applicationId) {
        $app = JobApplication::findOrFail($applicationId);
        $offer = $this->service->generateOfferLetter($app, $request->all());
        $app->update(['stage' => 'offer']);
        return response()->json(['message' => 'Offer letter sent', 'offer' => $offer]);
    }

    public function hire(Request $request, $applicationId) {
        $app = JobApplication::with('jobPosting')->findOrFail($applicationId);
        $employee = $this->service->hireApplicant($app, $request->all());
        $app->update(['stage' => 'hired']);
        return response()->json(['message' => 'Employee created from application', 'employee' => $employee], 201);
    }
}
