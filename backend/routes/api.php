<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\EmployeeController;
use App\Http\Controllers\API\DepartmentController;
use App\Http\Controllers\API\DesignationController;
use App\Http\Controllers\API\PayrollController;
use App\Http\Controllers\API\LeaveController;
use App\Http\Controllers\API\ExcuseLimitController;
use App\Http\Controllers\API\LoanController;
use App\Http\Controllers\API\SeparationController;
use App\Http\Controllers\API\RequestManagementController;
use App\Http\Controllers\API\AdminController;
use App\Http\Controllers\API\AttendanceController;
use App\Http\Controllers\API\RecruitmentController;
use App\Http\Controllers\API\OnboardingController;
use App\Http\Controllers\API\PerformanceController;
use App\Http\Controllers\API\OrgChartController;
use App\Http\Controllers\API\DashboardController;

/*
|--------------------------------------------------------------------------
| API Routes — HRMS v1
| All routes accessible at /api/v1/...
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ── Public Auth ──────────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('login',           [AuthController::class, 'login']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password',  [AuthController::class, 'resetPassword']);
    });

    // ── Public Job Listings ──────────────────────────────────────────────
    Route::get('jobs',                    [RecruitmentController::class, 'publicJobs']);
    Route::post('jobs/{jobId}/apply',     [RecruitmentController::class, 'publicApply']);

    // ── Protected Routes ─────────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::prefix('auth')->group(function () {
            Route::post('logout',   [AuthController::class, 'logout']);
            Route::get('me',        [AuthController::class, 'me']);
            Route::put('password',  [AuthController::class, 'changePassword']);
        });

        // Dashboard
        Route::get('dashboard/stats',             [DashboardController::class, 'stats']);
        Route::get('dashboard/charts',            [DashboardController::class, 'charts']);
        Route::get('dashboard/recent-activities', [DashboardController::class, 'recentActivities']);

        // Employees
        Route::prefix('employees')->group(function () {
            Route::get('/',                              [EmployeeController::class, 'index']);
            Route::post('/',                             [EmployeeController::class, 'store']);
            Route::get('/export',                        [EmployeeController::class, 'export']);
            Route::get('/{id}',                          [EmployeeController::class, 'show']);
            Route::put('/{id}',                          [EmployeeController::class, 'update']);
            Route::delete('/{id}',                       [EmployeeController::class, 'destroy']);
            Route::post('/{id}/avatar',                  [EmployeeController::class, 'uploadAvatar']);
            Route::post('/{id}/documents',               [EmployeeController::class, 'uploadDocument']);
            Route::get('/{id}/documents',                [EmployeeController::class, 'listDocuments']);
            Route::delete('/{id}/documents/{docId}',     [EmployeeController::class, 'deleteDocument']);
            Route::get('/{id}/documents/{docId}/download', [EmployeeController::class, 'downloadDocument']);
        });

        // Departments
        Route::prefix('departments')->group(function () {
            Route::get('/',               [DepartmentController::class, 'index']);
            Route::post('/',              [DepartmentController::class, 'store']);
            Route::get('/{id}',           [DepartmentController::class, 'show']);
            Route::put('/{id}',           [DepartmentController::class, 'update']);
            Route::delete('/{id}',        [DepartmentController::class, 'destroy']);
            Route::get('/{id}/headcount', [DepartmentController::class, 'headcount']);
        });

        // Designations
        Route::apiResource('designations', DesignationController::class);

        // Payroll
        Route::prefix('payroll')->group(function () {
            Route::get('/',                                [PayrollController::class, 'index']);
            Route::post('/run',                            [PayrollController::class, 'run']);
            Route::get('/components',                      [PayrollController::class, 'components']);
            Route::post('/components',                     [PayrollController::class, 'storeComponent']);
            Route::get('/{id}',                            [PayrollController::class, 'show']);
            Route::post('/{id}/approve',                   [PayrollController::class, 'approve']);
            Route::post('/{id}/reject',                    [PayrollController::class, 'reject']);
            Route::get('/{id}/payslips',                   [PayrollController::class, 'payslips']);
            Route::get('/{id}/export',                     [PayrollController::class, 'export']);
            Route::put('/{id}/payslips/{psId}',            [PayrollController::class, 'updatePayslip']);
            Route::post('/{id}/reopen',                    [PayrollController::class, 'reopen']);
            Route::post('/{id}/recalculate',               [PayrollController::class, 'recalculate']);
            Route::get('/employee/{empId}',                [PayrollController::class, 'employeeHistory']);
            Route::get('/payslip/{payslipId}/download',    [PayrollController::class, 'downloadPayslip']);
        });

        // Leave
        Route::prefix('leave')->group(function () {
            Route::post('/accrue',   [LeaveController::class, 'runAccrual']);   // manual trigger
            Route::get('/types',                   [LeaveController::class, 'types']);
            Route::post('/types',                  [LeaveController::class, 'storeType']);
            Route::put('/types/{id}',              [LeaveController::class, 'updateType']);
            Route::get('/requests',                [LeaveController::class, 'index']);
            Route::post('/requests',               [LeaveController::class, 'store']);
            Route::get('/requests/{id}',           [LeaveController::class, 'show']);
            Route::put('/requests/{id}',           [LeaveController::class, 'update']);
            Route::delete('/requests/{id}',        [LeaveController::class, 'cancel']);
            Route::post('/requests/{id}/approve',  [LeaveController::class, 'approve']);
            Route::post('/requests/{id}/reject',   [LeaveController::class, 'reject']);
            Route::get('/balance/{empId}',         [LeaveController::class, 'balance']);
            Route::get('/calendar',                [LeaveController::class, 'calendar']);
            Route::get('/stats',                   [LeaveController::class, 'stats']);
            Route::get('/excuse-usage',            [LeaveController::class, 'excuseUsage']);

        // Department excuse limits (admin)
        Route::prefix('excuse-limits')->group(function () {
            Route::get('/',        [ExcuseLimitController::class, 'index']);
            Route::post('/bulk',   [ExcuseLimitController::class, 'bulkUpsert']);
            Route::put('/{id}',    [ExcuseLimitController::class, 'update']);
        });
            Route::get('/all-balances',            [LeaveController::class, 'allBalances']);
            Route::get('/holidays',                [LeaveController::class, 'holidays']);
            Route::post('/holidays',               [LeaveController::class, 'storeHoliday']);
            Route::delete('/holidays/{id}',        [LeaveController::class, 'deleteHoliday']);
        });

        // Attendance
        Route::prefix('attendance')->group(function () {
            Route::post('/checkin',           [AttendanceController::class, 'checkIn']);
            Route::post('/checkout',          [AttendanceController::class, 'checkOut']);
            Route::get('/today',              [AttendanceController::class, 'today']);
            Route::get('/dashboard',          [AttendanceController::class, 'dashboard']);
            Route::get('/report',             [AttendanceController::class, 'report']);
            Route::post('/manual',            [AttendanceController::class, 'manualEntry']);
            Route::get('/employee/{empId}',   [AttendanceController::class, 'employeeLog']);
        });

        // Recruitment
        Route::prefix('recruitment')->group(function () {
            Route::get('/jobs',                        [RecruitmentController::class, 'jobs']);
            Route::post('/jobs',                       [RecruitmentController::class, 'storeJob']);
            Route::put('/jobs/{id}',                   [RecruitmentController::class, 'updateJob']);
            Route::delete('/jobs/{id}',                [RecruitmentController::class, 'deleteJob']);
            Route::post('/apply/{jobId}',              [RecruitmentController::class, 'apply']);
            Route::get('/applications',                [RecruitmentController::class, 'applications']);
            Route::get('/applications/{id}',           [RecruitmentController::class, 'showApplication']);
            Route::put('/applications/{id}/stage',     [RecruitmentController::class, 'updateStage']);
            Route::post('/interviews',                 [RecruitmentController::class, 'scheduleInterview']);
            Route::put('/interviews/{id}',             [RecruitmentController::class, 'updateInterview']);
            Route::post('/offer/{applicationId}',      [RecruitmentController::class, 'sendOffer']);
            Route::post('/hire/{applicationId}',       [RecruitmentController::class, 'hire']);
        });

        // Onboarding
        Route::prefix('onboarding')->group(function () {
            Route::get('/{empId}/tasks',            [OnboardingController::class, 'tasks']);
            Route::post('/{empId}/tasks',           [OnboardingController::class, 'createTask']);
            Route::put('/tasks/{taskId}',           [OnboardingController::class, 'updateTask']);
            Route::post('/tasks/{taskId}/complete', [OnboardingController::class, 'completeTask']);
        });

        // Performance
        Route::prefix('performance')->group(function () {
            Route::get('/reviews',                   [PerformanceController::class, 'index']);
            Route::post('/reviews',                  [PerformanceController::class, 'store']);
            Route::get('/reviews/{id}',              [PerformanceController::class, 'show']);
            Route::post('/reviews/{id}/self',        [PerformanceController::class, 'selfAssessment']);
            Route::post('/reviews/{id}/manager',     [PerformanceController::class, 'managerReview']);
            Route::post('/reviews/{id}/finalize',    [PerformanceController::class, 'finalize']);
            Route::get('/kpis',                      [PerformanceController::class, 'kpis']);
            Route::post('/kpis',                     [PerformanceController::class, 'storeKpi']);
            Route::put('/kpis/{id}',                 [PerformanceController::class, 'updateKpi']);
            Route::get('/reports/{empId}',           [PerformanceController::class, 'report']);
        });

        // Org Chart
        Route::prefix('org-chart')->group(function () {
            Route::get('/',             [OrgChartController::class, 'index']);
            Route::get('/stats',        [OrgChartController::class, 'stats']);
            Route::get('/search',       [OrgChartController::class, 'search']);
            Route::get('/dept/{id}',    [OrgChartController::class, 'department'])->whereNumber('id');
            Route::post('/dept',        [OrgChartController::class, 'storeDepartment']);
            Route::put('/dept/{id}',    [OrgChartController::class, 'updateDepartment'])->whereNumber('id');
        });

        // ── Loans ────────────────────────────────────────────────────────────
        Route::prefix('loans')->group(function () {
            // ── Static routes FIRST (must come before any {id} wildcards) ──
            Route::get('/stats',                            [LoanController::class, 'stats']);
            Route::get('/my',                               [LoanController::class, 'myLoans']);
            Route::post('/installments/mark-overdue',       [LoanController::class, 'markOverdue']);

            // ── Loan Types ────────────────────────────────────────────────
            Route::prefix('types')->group(function () {
                Route::get('/',         [LoanController::class, 'types']);
                Route::get('/all',      [LoanController::class, 'allTypes']);
                Route::post('/',        [LoanController::class, 'storeType']);
                Route::put('/{id}',     [LoanController::class, 'updateType'])->whereNumber('id');
            });

            // ── Loan CRUD ─────────────────────────────────────────────────
            Route::get('/',     [LoanController::class, 'index']);
            Route::post('/',    [LoanController::class, 'store']);

            // ── Numeric-ID routes ─────────────────────────────────────────
            Route::get('/{id}',                             [LoanController::class, 'show'])->whereNumber('id');
            Route::post('/{id}/approve',                    [LoanController::class, 'approve'])->whereNumber('id');
            Route::post('/{id}/reject',                     [LoanController::class, 'reject'])->whereNumber('id');
            Route::post('/{id}/cancel',                     [LoanController::class, 'cancel'])->whereNumber('id');
            Route::post('/{id}/disburse',                   [LoanController::class, 'disburse'])->whereNumber('id');
            Route::post('/{loanId}/installments/{instId}/pay',  [LoanController::class, 'payInstallment'])->whereNumber('loanId')->whereNumber('instId');
            Route::post('/{loanId}/installments/{instId}/skip', [LoanController::class, 'skipInstallment'])->whereNumber('loanId')->whereNumber('instId');
        });

        // ── Separations / Offboarding ────────────────────────────────────────
        Route::prefix('separations')->group(function () {
            Route::get('/stats',                                    [SeparationController::class, 'stats']);
            Route::get('/settlement-preview',                       [SeparationController::class, 'settlementPreview']);

            Route::prefix('templates')->group(function () {
                Route::get('/',         [SeparationController::class, 'templates']);
                Route::post('/',        [SeparationController::class, 'storeTemplate']);
                Route::put('/{id}',     [SeparationController::class, 'updateTemplate'])->whereNumber('id');
                Route::delete('/{id}',  [SeparationController::class, 'deleteTemplate'])->whereNumber('id');
            });

            Route::get('/',     [SeparationController::class, 'index']);
            Route::post('/',    [SeparationController::class, 'store']);
            Route::get('/{id}',                         [SeparationController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',                         [SeparationController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/approve',                [SeparationController::class, 'approve'])->whereNumber('id');
            Route::post('/{id}/reject',                 [SeparationController::class, 'reject'])->whereNumber('id');
            Route::post('/{id}/cancel',                 [SeparationController::class, 'cancel'])->whereNumber('id');
            Route::post('/{id}/complete',               [SeparationController::class, 'complete'])->whereNumber('id');
            Route::put('/{id}/settlement',              [SeparationController::class, 'updateSettlement'])->whereNumber('id');
            Route::post('/{id}/exit-interview',         [SeparationController::class, 'updateExitInterview'])->whereNumber('id');
            Route::put('/{id}/checklist/{itemId}',      [SeparationController::class, 'updateChecklistItem'])->whereNumber('id')->whereNumber('itemId');
        });


        // ── Request Management ───────────────────────────────────────────────
        Route::prefix('requests')->group(function () {
            Route::get('/stats',                    [RequestManagementController::class, 'stats']);
            Route::post('/mark-overdue',            [RequestManagementController::class, 'markOverdue']);

            Route::prefix('types')->group(function () {
                Route::get('/',         [RequestManagementController::class, 'types']);
                Route::get('/all',      [RequestManagementController::class, 'allTypes']);
                Route::post('/',        [RequestManagementController::class, 'storeType']);
                Route::put('/{id}',     [RequestManagementController::class, 'updateType'])->whereNumber('id');
            });

            Route::get('/',     [RequestManagementController::class, 'index']);
            Route::post('/',    [RequestManagementController::class, 'store']);
            Route::get('/{id}',                         [RequestManagementController::class, 'show'])->whereNumber('id');
            Route::post('/{id}/manager-approve',        [RequestManagementController::class, 'managerApprove'])->whereNumber('id');
            Route::post('/{id}/assign',                 [RequestManagementController::class, 'assign'])->whereNumber('id');
            Route::post('/{id}/complete',               [RequestManagementController::class, 'complete'])->whereNumber('id');
            Route::post('/{id}/reject',                 [RequestManagementController::class, 'reject'])->whereNumber('id');
            Route::post('/{id}/cancel',                 [RequestManagementController::class, 'cancel'])->whereNumber('id');
            Route::post('/{id}/comments',               [RequestManagementController::class, 'addComment'])->whereNumber('id');
        });


        // ── Admin / RBAC ──────────────────────────────────────────────────────
        Route::prefix('admin')->group(function () {
            Route::get('/overview',                         [AdminController::class, 'overview']);
            Route::get('/permissions',                      [AdminController::class, 'permissions']);

            Route::prefix('users')->group(function () {
                Route::get('/',         [AdminController::class, 'users']);
                Route::post('/',        [AdminController::class, 'storeUser']);
                Route::get('/{id}',     [AdminController::class, 'showUser'])->whereNumber('id');
                Route::put('/{id}',     [AdminController::class, 'updateUser'])->whereNumber('id');
                Route::post('/{id}/assign-role',    [AdminController::class, 'assignRole'])->whereNumber('id');
                Route::post('/{id}/toggle-status',  [AdminController::class, 'toggleUserStatus'])->whereNumber('id');
            });

            Route::prefix('roles')->group(function () {
                Route::get('/',         [AdminController::class, 'roles']);
                Route::put('/{id}/permissions', [AdminController::class, 'updateRolePermissions'])->whereNumber('id');
            });
        });

    });
});

