<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\{JobPosting, JobApplication, Interview};
use App\Services\RecruitmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecruitmentController extends Controller {
    protected $service;
    public function __construct(RecruitmentService $service) { $this->service = $service; }

    public function jobs(Request $request) {
        $jobs = JobPosting::with(['department','designation'])
            ->withCount('applications')
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->department_id, fn($q) => $q->where('department_id', $request->department_id))
            ->when($request->search, fn($q) => $q->where('title','like',"%{$request->search}%"))
            ->orderBy('created_at','desc')
            ->paginate((int)($request->per_page ?? 15));
        return response()->json($jobs);
    }

    public function stats(): \Illuminate\Http\JsonResponse
    {
        $safe = fn($fn) => rescue($fn, 0, false);
        return response()->json([
            'open_jobs'       => $safe(fn() => JobPosting::where('status','open')->count()),
            'total_jobs'      => $safe(fn() => JobPosting::count()),
            'total_applicants'=> $safe(fn() => JobApplication::count()),
            'new_this_week'   => $safe(fn() => JobApplication::where('created_at','>=',now()->subDays(7))->count()),
            'in_interview'    => $safe(fn() => JobApplication::where('stage','interview')->count()),
            'offers_sent'     => $safe(fn() => JobApplication::where('stage','offer')->count()),
            'hired'           => $safe(fn() => JobApplication::where('stage','hired')->count()),
            'rejected'        => $safe(fn() => JobApplication::where('stage','rejected')->count()),
        ]);
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
            'status'          => 'sometimes|in:draft,open,closed,on_hold',
            'vacancies'       => 'sometimes|integer|min:1',
            'department_id'   => 'nullable|exists:departments,id',
            'designation_id'  => 'nullable|exists:designations,id',
            'salary_min'      => 'nullable|numeric|min:0',
            'salary_max'      => 'nullable|numeric|min:0',
            'closing_date'    => 'nullable|date|after:today',
        ]);
        $job = JobPosting::create(array_merge($request->all(), [
            'created_by' => auth()->id(),
            'status'     => $request->status ?? 'open',
        ]));
        return response()->json(['job' => $job->load('department','designation')], 201);
    }

    public function updateJob(Request $request, $id) {
        $job = JobPosting::findOrFail($id);
        $job->update($request->all());
        return response()->json(['job' => $job]);
    }

    public function deleteJob($id) {
        JobPosting::findOrFail($id)->delete();
        return response()->json(['message' => 'Job posting deleted']);
    }

    public function apply(Request $request, $jobId) {
        $request->validate([
            'applicant_name'  => 'required|string|max:150',
            'applicant_email' => 'required|email|max:191',
            'applicant_phone' => 'nullable|string|max:20',
            'cv_path'         => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'expected_salary' => 'nullable|numeric',
            'available_from'  => 'nullable|date',
            'cover_letter_text' => 'nullable|string',
            'stage'           => 'nullable|in:applied,screening,interview,offer',
        ]);

        // HR can add applicants to any job; public applicants can only apply to open jobs
        $isHR = auth()->check() && rescue(fn() => \DB::table('model_has_roles')
            ->join('roles','roles.id','=','model_has_roles.role_id')
            ->where('model_has_roles.model_id', auth()->id())
            ->whereIn('roles.name', ['super_admin','hr_manager','hr_staff'])
            ->exists(), false, false);

        $job = $isHR
            ? JobPosting::findOrFail($jobId)
            : JobPosting::where('status','open')->findOrFail($jobId);

        $cvPath = $request->hasFile('cv_path')
            ? $request->file('cv_path')->store("recruitment/cvs/{$jobId}", 'public')
            : null;

        $application = JobApplication::create(array_merge(
            $request->only(['applicant_name','applicant_email','applicant_phone','expected_salary','available_from','cover_letter_text']),
            [
                'job_posting_id' => $jobId,
                'cv_path'        => $cvPath,
                'stage'          => $request->stage ?? 'applied',
            ]
        ));

        return response()->json(['message' => 'Application submitted', 'application' => $application->load('jobPosting')], 201);
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
        $request->validate([
            'hire_date'       => 'required|date',
            'salary'          => 'required|numeric|min:0',
            'department_id'   => 'nullable|exists:departments,id',
            'designation_id'  => 'nullable|exists:designations,id',
            'manager_id'      => 'nullable|exists:employees,id',
            'employment_type' => 'nullable|in:full_time,part_time,contract,intern',
            'probation_period'=> 'nullable|integer|min:0|max:365',
            'custom_tasks'    => 'nullable|array',
            'custom_tasks.*'  => 'string|max:200',
        ]);

        $app    = JobApplication::with('jobPosting')->findOrFail($applicationId);
        $result = $this->service->hireApplicant($app, $request->all());
        $app->update(['stage' => 'hired']);

        if ($app->jobPosting) {
            $app->jobPosting->update(['status' => 'closed']);
        }

        return response()->json([
            'message'          => 'Employee record created successfully.',
            'employee'         => $result['employee'],
            'employee_code'    => $result['employee_code'],
            'login_email'      => $result['login_email'],
            'temp_password'    => $result['temp_password'],
            'is_new_account'   => $result['is_new'],
            'onboarding_tasks' => $result['onboarding_tasks'],
        ], 201);
    }

    // ══════════════════════════════════════════════════════════════════════
    // CV BANK
    // ══════════════════════════════════════════════════════════════════════

    /** List all CV bank entries (not linked to a specific job) */
    public function cvBank(Request $request) {
        $cvs = JobApplication::with('jobPosting')
            ->where('is_cv_bank', true)
            ->when($request->search, fn($q) =>
                $q->where('applicant_name', 'like', "%{$request->search}%")
                  ->orWhere('applicant_email', 'like', "%{$request->search}%")
                  ->orWhere('position_applied', 'like', "%{$request->search}%")
                  ->orWhere('skills', 'like', "%{$request->search}%")
            )
            ->when($request->rating,  fn($q) => $q->where('rating', $request->rating))
            ->when($request->source,  fn($q) => $q->where('source', $request->source))
            ->orderBy('created_at', 'desc')
            ->paginate((int)($request->per_page ?? 20));
        return response()->json($cvs);
    }

    /** Add a CV to the bank (no specific job) */
    public function storeCv(Request $request) {
        $request->validate([
            'applicant_name'    => 'required|string|max:150',
            'applicant_email'   => 'required|email|max:191',
            'applicant_phone'   => 'nullable|string|max:20',
            'position_applied'  => 'nullable|string|max:150',
            'nationality'       => 'nullable|string|max:100',
            'experience_years'  => 'nullable|integer|min:0|max:50',
            'skills'            => 'nullable|string',
            'source'            => 'nullable|string|max:80',
            'expected_salary'   => 'nullable|numeric',
            'available_from'    => 'nullable|date',
            'notes'             => 'nullable|string',
            'cv_file'           => 'nullable|file|mimes:pdf,doc,docx|max:5120',
        ]);

        $cvPath = null;
        if ($request->hasFile('cv_file')) {
            $cvPath = $request->file('cv_file')->store('recruitment/cv-bank', 'public');
        }

        $cv = JobApplication::create(array_merge(
            $request->except('cv_file'),
            [
                'cv_path'    => $cvPath,
                'is_cv_bank' => true,
                'stage'      => 'applied',
                'rating'     => $request->rating ?? 'hold',
            ]
        ));

        return response()->json(['cv' => $cv], 201);
    }

    /** Update rating/notes for a CV bank entry */
    public function updateCv(Request $request, $id) {
        $cv = JobApplication::where('is_cv_bank', true)->findOrFail($id);
        $cv->update($request->only(['rating', 'notes', 'skills', 'position_applied',
                                     'experience_years', 'expected_salary', 'source',
                                     'nationality', 'available_from']));
        return response()->json(['cv' => $cv]);
    }

    /** Delete a CV bank entry */
    public function deleteCv($id) {
        $cv = JobApplication::where('is_cv_bank', true)->findOrFail($id);
        if ($cv->cv_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($cv->cv_path);
        }
        $cv->delete();
        return response()->json(['message' => 'CV removed from bank.']);
    }

    /** Move a CV bank entry to a job application */
    public function linkCvToJob(Request $request, $id) {
        $request->validate(['job_posting_id' => 'required|exists:job_postings,id']);
        $cv = JobApplication::where('is_cv_bank', true)->findOrFail($id);
        $cv->update([
            'job_posting_id' => $request->job_posting_id,
            'is_cv_bank'     => false,
            'stage'          => 'applied',
        ]);
        return response()->json(['message' => 'CV linked to job posting.', 'application' => $cv]);
    }
}
