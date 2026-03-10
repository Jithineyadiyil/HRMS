<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\JobPosting;
use App\Models\JobApplication;
use App\Models\AttendanceLog;
use App\Models\PerformanceReview;
use App\Models\Department;

class DashboardController extends Controller {
    public function stats() {
        $today = now()->toDateString();
        return response()->json([
            'total_employees'  => Employee::where('status','active')->count(),
            'new_this_month'   => Employee::whereMonth('hire_date', now()->month)->count(),
            'on_leave_today'   => LeaveRequest::where('status','approved')->where('start_date','<=',$today)->where('end_date','>=',$today)->count(),
            'pending_leaves'   => LeaveRequest::where('status','pending')->count(),
            'present_today'    => AttendanceLog::where('date',$today)->where('status','present')->count(),
            'open_jobs'        => JobPosting::where('status','open')->count(),
            'new_applications' => JobApplication::whereDate('created_at','>',now()->subDays(7))->count(),
            'pending_reviews'  => PerformanceReview::where('status','pending')->count(),
            'dept_headcount'   => Department::withCount('employees')->get()->map(fn($d)=>['name'=>$d->name,'count'=>$d->employees_count]),
        ]);
    }
}
