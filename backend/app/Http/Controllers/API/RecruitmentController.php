<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\{JobPosting, JobApplication, Interview};
use App\Services\RecruitmentService;
use Illuminate\Http\Request;

class RecruitmentController extends Controller {
    protected $service;
    public function __construct(RecruitmentService $service) { $this->service = $service; }

    public function jobs(Request $request) {
        $jobs = JobPosting::with(['department','designation'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->department_id, fn($q) => $q->where('department_id', $request->department_id))
            ->orderBy('created_at','desc')->paginate(15);
        return response()->json($jobs);
    }

    public function publicJobs() {
        $jobs = JobPosting::with(['department'])->where('status','open')->where(function($q){ $q->whereNull('closing_date')->orWhere('closing_date','>=',now()); })->get();
        return response()->json(['jobs' => $jobs]);
    }

    public function storeJob(Request $request) {
        $request->validate(['title'=>'required|string|max:150','employment_type'=>'required','description'=>'required']);
        $job = JobPosting::create(array_merge($request->all(), ['created_by' => auth()->id()]));
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
        $request->validate(['applicant_name'=>'required','applicant_email'=>'required|email','cv_path'=>'nullable|file|mimes:pdf,doc,docx|max:5120']);
        $job = JobPosting::where('status','open')->findOrFail($jobId);
        $cvPath = $request->hasFile('cv_path') ? $request->file('cv_path')->store("recruitment/cvs/{$jobId}") : null;
        $application = JobApplication::create(array_merge($request->except('cv_path'), ['job_posting_id' => $jobId, 'cv_path' => $cvPath]));
        return response()->json(['message' => 'Application submitted', 'application' => $application], 201);
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
