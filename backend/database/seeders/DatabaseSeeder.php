<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Department;
use App\Models\LoanType;
use App\Models\OffboardingTemplate;
use App\Models\RequestType;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\PayrollComponent;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder {
    public function run() {
        // ── Roles ─────────────────────────────────────────────────────────
        $roles = ['super_admin', 'hr_manager', 'manager', 'employee', 'recruiter', 'finance'];
        foreach ($roles as $role) Role::firstOrCreate(['name' => $role]);

        // ── Permissions ───────────────────────────────────────────────────
        $permissions = [
            'view_employees','create_employees','edit_employees','delete_employees',
            'view_payroll','run_payroll','approve_payroll',
            'view_leave','manage_leave','approve_leave',
            'view_attendance','manage_attendance',
            'view_recruitment','manage_recruitment',
            'view_performance','manage_performance',
            'view_reports','manage_settings',
        ];
        foreach ($permissions as $perm) Permission::firstOrCreate(['name' => $perm]);

        // Assign all permissions to super_admin
        Role::findByName('super_admin')->givePermissionTo(Permission::all());

        // hr_manager permissions
        Role::findByName('hr_manager')->givePermissionTo([
            'view_employees','create_employees','edit_employees',
            'view_payroll','run_payroll','approve_payroll',
            'view_leave','manage_leave','approve_leave',
            'view_attendance','manage_attendance',
            'view_recruitment','manage_recruitment',
            'view_performance','manage_performance',
            'view_reports',
        ]);

        // manager permissions
        Role::findByName('manager')->givePermissionTo([
            'view_employees','view_leave','approve_leave',
            'view_attendance','view_performance','manage_performance',
        ]);

        // employee permissions
        Role::findByName('employee')->givePermissionTo([
            'view_leave','view_attendance',
        ]);

        // recruiter permissions
        Role::findByName('recruiter')->givePermissionTo([
            'view_employees','view_recruitment','manage_recruitment',
        ]);

        // finance permissions
        Role::findByName('finance')->givePermissionTo([
            'view_payroll','approve_payroll','view_reports',
        ]);

        // ── Departments ───────────────────────────────────────────────────
        $departments = [
            ['name' => 'Human Resources',      'code' => 'HR',  'headcount_budget' => 10],
            ['name' => 'Information Technology','code' => 'IT',  'headcount_budget' => 20],
            ['name' => 'Finance',               'code' => 'FIN', 'headcount_budget' => 8],
            ['name' => 'Operations',            'code' => 'OPS', 'headcount_budget' => 25],
            ['name' => 'Sales & Marketing',     'code' => 'SM',  'headcount_budget' => 15],
            ['name' => 'Legal & Compliance',    'code' => 'LEG', 'headcount_budget' => 5],
            ['name' => 'Executive',             'code' => 'EXE', 'headcount_budget' => 3],
        ];
        foreach ($departments as $d) Department::firstOrCreate(['code' => $d['code']], $d);

        // ── Designations ──────────────────────────────────────────────────
        $hrDept  = Department::where('code','HR')->first();
        $itDept  = Department::where('code','IT')->first();
        $finDept = Department::where('code','FIN')->first();
        $opsDept = Department::where('code','OPS')->first();
        $exeDept = Department::where('code','EXE')->first();

        $designations = [
            ['title' => 'Chief Executive Officer',    'level' => 'executive',  'department_id' => $exeDept->id],
            ['title' => 'Chief Technology Officer',   'level' => 'executive',  'department_id' => $itDept->id],
            ['title' => 'HR Manager',                 'level' => 'management', 'department_id' => $hrDept->id],
            ['title' => 'HR Officer',                 'level' => 'staff',      'department_id' => $hrDept->id],
            ['title' => 'Software Engineer',          'level' => 'staff',      'department_id' => $itDept->id],
            ['title' => 'Senior Software Engineer',   'level' => 'senior',     'department_id' => $itDept->id],
            ['title' => 'Finance Manager',            'level' => 'management', 'department_id' => $finDept->id],
            ['title' => 'Accountant',                 'level' => 'staff',      'department_id' => $finDept->id],
            ['title' => 'Operations Manager',         'level' => 'management', 'department_id' => $opsDept->id],
            ['title' => 'Operations Coordinator',     'level' => 'staff',      'department_id' => $opsDept->id],
        ];
        foreach ($designations as $d) Designation::firstOrCreate(['title' => $d['title']], $d);

        // ── Leave Types ───────────────────────────────────────────────────
        $leaveTypes = [
            ['name' => 'Annual Leave',    'code' => 'AL',  'days_allowed' => 22, 'is_paid' => true,  'carry_forward' => true,  'max_carry_forward' => 5],
            ['name' => 'Sick Leave',      'code' => 'SL',  'days_allowed' => 10, 'is_paid' => true,  'carry_forward' => false, 'max_carry_forward' => 0],
            [
                'name' => 'Business Excuse', 'code' => 'BE', 'days_allowed' => 0,
                'is_paid' => true, 'carry_forward' => false, 'max_carry_forward' => 0,
                'is_hourly' => true, 'monthly_hours_limit' => 12.0,
                'exempt_department_codes' => json_encode(['SM']),
                'description' => 'Hourly excuse for business purposes. Sales team: unlimited. Others: 12h/month max.',
                'is_active' => true,
            ],
            ['name' => 'Maternity Leave', 'code' => 'ML',  'days_allowed' => 90, 'is_paid' => true,  'carry_forward' => false, 'max_carry_forward' => 0],
            ['name' => 'Paternity Leave', 'code' => 'PL',  'days_allowed' => 5,  'is_paid' => true,  'carry_forward' => false, 'max_carry_forward' => 0],
            ['name' => 'Unpaid Leave',    'code' => 'UL',  'days_allowed' => 30, 'is_paid' => false, 'carry_forward' => false, 'max_carry_forward' => 0],
            ['name' => 'Emergency Leave', 'code' => 'EML', 'days_allowed' => 3,  'is_paid' => true,  'carry_forward' => false, 'max_carry_forward' => 0],
        ];
        foreach ($leaveTypes as $lt) LeaveType::firstOrCreate(['code' => $lt['code']], $lt);

        // ── Payroll Components ────────────────────────────────────────────
        // Saudi payroll components
        // Note: Basic, HRA, TA, and GOSI are handled directly in PayrollService.
        // Add extra components here (bonuses, deductions, loans etc.)
        $components = [
            ['name' => 'Performance Bonus',   'code' => 'PB',   'type' => 'earning',   'calculation' => 'percentage', 'value' => 0,  'is_taxable' => false, 'is_active' => false, 'description' => 'Discretionary performance bonus (% of basic)'],
            ['name' => 'Mobile Allowance',    'code' => 'MOB',  'type' => 'earning',   'calculation' => 'fixed',      'value' => 0,  'is_taxable' => false, 'is_active' => false, 'description' => 'Monthly mobile allowance (SAR)'],
            ['name' => 'Loan Deduction',      'code' => 'LOAN', 'type' => 'deduction', 'calculation' => 'fixed',      'value' => 0,  'is_taxable' => false, 'is_active' => false, 'description' => 'Monthly loan repayment deduction (SAR)'],
            ['name' => 'Penalty Deduction',   'code' => 'PEN',  'type' => 'deduction', 'calculation' => 'fixed',      'value' => 0,  'is_taxable' => false, 'is_active' => false, 'description' => 'Disciplinary / penalty deduction (SAR)'],
        ];
        foreach ($components as $c) PayrollComponent::firstOrCreate(['code' => $c['code']], $c);

        // ── Admin User ────────────────────────────────────────────────────
        $hrDesig = Designation::where('title','HR Manager')->first();
        $hrDept  = Department::where('code','HR')->first();

        $admin = User::firstOrCreate(
            ['email' => 'admin@hrms.com'],
            [
                'name'     => 'System Admin',
                'password' => Hash::make('Admin@1234'),
            ]
        );
        $admin->assignRole('super_admin');

        Employee::updateOrCreate(
            ['employee_code' => 'EMP0001'],
            [
                'user_id'         => $admin->id,
                'department_id'   => $hrDept?->id,
                'designation_id'  => $hrDesig?->id,
                'first_name'      => 'System',
                'last_name'       => 'Admin',
                'email'           => 'admin@hrms.com',
                'hire_date'       => now(),
                'employment_type' => 'full_time',
                'status'          => 'active',
                'salary'          => 5000,
            ]
        );

        $this->command->info('✅ HRMS seeded successfully!');
        $this->command->info('   Login: admin@hrms.com / Admin@1234');

        // ── Loan Types ────────────────────────────────────────────────────
        $loanTypes = [
            ['name'=>'Personal Loan',      'code'=>'PL',  'max_amount'=>50000,  'max_installments'=>12, 'interest_rate'=>0,   'description'=>'General purpose personal loan, interest-free.'],
            ['name'=>'Housing Loan',        'code'=>'HL',  'max_amount'=>200000, 'max_installments'=>12, 'interest_rate'=>0,   'description'=>'For housing expenses and rent deposits.'],
            ['name'=>'Emergency Loan',      'code'=>'EL',  'max_amount'=>20000,  'max_installments'=>6,  'interest_rate'=>0,   'description'=>'Fast-track emergency loan, max 6 months.'],
            ['name'=>'Education Loan',      'code'=>'EDL', 'max_amount'=>30000,  'max_installments'=>12, 'interest_rate'=>0,   'description'=>'For employee or dependent education costs.'],
            ['name'=>'Vehicle Loan',        'code'=>'VL',  'max_amount'=>80000,  'max_installments'=>12, 'interest_rate'=>3.5, 'description'=>'Vehicle purchase or major repair.'],
        ];
        foreach ($loanTypes as $lt) {
            LoanType::firstOrCreate(['code' => $lt['code']], array_merge($lt, ['is_active' => true]));
        }


        // ── Offboarding Templates ─────────────────────────────────────────
        $templates = [
            // IT
            ['title'=>'Return laptop / workstation',      'category'=>'it',      'sort_order'=>1,  'is_required'=>true],
            ['title'=>'Return mobile phone / SIM card',   'category'=>'it',      'sort_order'=>2,  'is_required'=>true],
            ['title'=>'Revoke system / application access','category'=>'it',     'sort_order'=>3,  'is_required'=>true],
            ['title'=>'Disable email account',            'category'=>'it',      'sort_order'=>4,  'is_required'=>true],
            ['title'=>'Transfer data / project files',    'category'=>'it',      'sort_order'=>5,  'is_required'=>true],
            // HR
            ['title'=>'Complete exit interview',          'category'=>'hr',      'sort_order'=>10, 'is_required'=>true],
            ['title'=>'Return ID / access card',          'category'=>'hr',      'sort_order'=>11, 'is_required'=>true],
            ['title'=>'Return employee handbook',         'category'=>'hr',      'sort_order'=>12, 'is_required'=>false],
            ['title'=>'Sign NDAs / non-compete docs',     'category'=>'hr',      'sort_order'=>13, 'is_required'=>true],
            ['title'=>'Update HR records / GOSI',        'category'=>'hr',       'sort_order'=>14, 'is_required'=>true],
            // Finance
            ['title'=>'Clear outstanding loans',          'category'=>'finance', 'sort_order'=>20, 'is_required'=>true],
            ['title'=>'Return petty cash / advances',     'category'=>'finance', 'sort_order'=>21, 'is_required'=>true],
            ['title'=>'Process final settlement',         'category'=>'finance', 'sort_order'=>22, 'is_required'=>true],
            ['title'=>'Return company credit card',       'category'=>'finance', 'sort_order'=>23, 'is_required'=>false],
            // Admin
            ['title'=>'Return car / parking pass',        'category'=>'admin',   'sort_order'=>30, 'is_required'=>false],
            ['title'=>'Return office keys',               'category'=>'admin',   'sort_order'=>31, 'is_required'=>true],
            ['title'=>'Return uniforms / equipment',      'category'=>'admin',   'sort_order'=>32, 'is_required'=>false],
            ['title'=>'Knowledge transfer to successor',  'category'=>'admin',   'sort_order'=>33, 'is_required'=>true],
        ];
        foreach ($templates as $t) {
            OffboardingTemplate::firstOrCreate(['title' => $t['title']], array_merge($t, ['is_active' => true]));
        }


        // ── Request Types ─────────────────────────────────────────────────
        $requestTypes = [
            // Visa & Travel
            ['name'=>'Exit Re-entry Visa (Single)',  'code'=>'VISA_EXIT_S',  'category'=>'visa',     'icon'=>'flight_takeoff',  'color'=>'#3b82f6', 'sla_days'=>5,  'sort_order'=>1,  'instructions'=>'Please provide your passport copy, ID copy, and intended travel dates.'],
            ['name'=>'Exit Re-entry Visa (Multiple)','code'=>'VISA_EXIT_M',  'category'=>'visa',     'icon'=>'flight_takeoff',  'color'=>'#3b82f6', 'sla_days'=>7,  'sort_order'=>2,  'instructions'=>'Provide passport copy, ID copy, duration needed, and travel purpose.'],
            ['name'=>'Visit Visa for Family',        'code'=>'VISA_FAMILY',  'category'=>'visa',     'icon'=>'family_restroom', 'color'=>'#6366f1', 'sla_days'=>7,  'sort_order'=>3,  'instructions'=>'Provide family member details (name, passport, relationship) and visit duration.'],
            ['name'=>'Business Visa Support Letter', 'code'=>'VISA_BIZ',     'category'=>'visa',     'icon'=>'business_center', 'color'=>'#0ea5e9', 'sla_days'=>3,  'sort_order'=>4,  'instructions'=>'Specify destination country, business purpose, and travel dates.'],
            // Travel
            ['name'=>'Air Ticket Request',           'code'=>'TRAVEL_TICKET','category'=>'travel',   'icon'=>'airplane_ticket', 'color'=>'#f59e0b', 'sla_days'=>3,  'sort_order'=>10, 'instructions'=>'Provide travel dates, destination, preferred airline if any, and reason for travel.'],
            ['name'=>'Air Ticket Allowance Letter',  'code'=>'TRAVEL_LETTER','category'=>'travel',   'icon'=>'mail',            'color'=>'#f59e0b', 'sla_days'=>2,  'sort_order'=>11, 'instructions'=>'Specify destination and travel dates for the allowance letter.'],
            // Documents & Certificates
            ['name'=>'Salary Certificate',           'code'=>'DOC_SALARY',   'category'=>'documents','icon'=>'payments',        'color'=>'#10b981', 'sla_days'=>2,  'sort_order'=>20, 'instructions'=>'Specify if required for bank, embassy, or other purpose. Mention language (Arabic/English).'],
            ['name'=>'Employment Certificate',       'code'=>'DOC_EMPLOY',   'category'=>'documents','icon'=>'badge',           'color'=>'#10b981', 'sla_days'=>2,  'sort_order'=>21, 'instructions'=>'Mention the purpose (bank, embassy, other) and required language.'],
            ['name'=>'Experience Letter',            'code'=>'DOC_EXP',      'category'=>'documents','icon'=>'workspace_premium','color'=>'#10b981','sla_days'=>3,  'sort_order'=>22, 'instructions'=>'Provide the addressee details if directed to a specific party.'],
            ['name'=>'NOC Letter',                   'code'=>'DOC_NOC',      'category'=>'documents','icon'=>'verified',        'color'=>'#10b981', 'sla_days'=>3,  'sort_order'=>23, 'instructions'=>'State the purpose of the NOC and to whom it is addressed.', 'requires_manager_approval'=>true],
            ['name'=>'Bank Letter',                  'code'=>'DOC_BANK',     'category'=>'documents','icon'=>'account_balance', 'color'=>'#10b981', 'sla_days'=>2,  'sort_order'=>24, 'instructions'=>'Mention your bank name, account details, and purpose of the letter.'],
            ['name'=>'Salary Transfer Letter',       'code'=>'DOC_SALARY_TR','category'=>'documents','icon'=>'swap_horiz',      'color'=>'#10b981', 'sla_days'=>2,  'sort_order'=>25, 'instructions'=>'Provide new bank name and account number for the transfer.'],
            ['name'=>'GOSI Certificate',             'code'=>'DOC_GOSI',     'category'=>'documents','icon'=>'health_and_safety','color'=>'#10b981','sla_days'=>3,  'sort_order'=>26, 'instructions'=>'Specify required for personal use or third party.'],
            // HR Requests
            ['name'=>'Advance Salary Request',       'code'=>'HR_ADVANCE',   'category'=>'hr',       'icon'=>'monetization_on', 'color'=>'#8b5cf6', 'sla_days'=>5,  'sort_order'=>30, 'requires_manager_approval'=>true, 'instructions'=>'State the advance amount needed and reason.'],
            ['name'=>'Change of Information',        'code'=>'HR_INFO',      'category'=>'hr',       'icon'=>'manage_accounts', 'color'=>'#8b5cf6', 'sla_days'=>3,  'sort_order'=>31, 'instructions'=>'Describe the information that needs to be updated and attach supporting documents.'],
            ['name'=>'Work From Home Request',       'code'=>'HR_WFH',       'category'=>'hr',       'icon'=>'home_work',       'color'=>'#8b5cf6', 'sla_days'=>2,  'sort_order'=>32, 'requires_manager_approval'=>true, 'instructions'=>'Specify dates and reason for WFH request.'],
            ['name'=>'Training Request',             'code'=>'HR_TRAIN',     'category'=>'hr',       'icon'=>'school',          'color'=>'#8b5cf6', 'sla_days'=>5,  'sort_order'=>33, 'requires_manager_approval'=>true, 'instructions'=>'Provide training name, provider, dates, and how it benefits your role.'],
            // IT
            ['name'=>'IT Equipment Request',         'code'=>'IT_EQUIP',     'category'=>'it',       'icon'=>'computer',        'color'=>'#ef4444', 'sla_days'=>5,  'sort_order'=>40, 'requires_manager_approval'=>true, 'instructions'=>'Specify equipment type, model if preferred, and business justification.'],
            ['name'=>'Software Access Request',      'code'=>'IT_ACCESS',    'category'=>'it',       'icon'=>'lock_open',       'color'=>'#ef4444', 'sla_days'=>3,  'sort_order'=>41, 'requires_manager_approval'=>true, 'instructions'=>'Specify the system/software name and the access level required.'],
            ['name'=>'Email / Account Setup',        'code'=>'IT_EMAIL',     'category'=>'it',       'icon'=>'email',           'color'=>'#ef4444', 'sla_days'=>2,  'sort_order'=>42, 'instructions'=>'Provide details of the account or email needed.'],
            // Admin
            ['name'=>'Parking Pass Request',         'code'=>'ADMIN_PARK',   'category'=>'admin',    'icon'=>'local_parking',   'color'=>'#ec4899', 'sla_days'=>3,  'sort_order'=>50, 'instructions'=>'Provide vehicle plate number, make, and model.'],
            ['name'=>'Business Card Request',        'code'=>'ADMIN_CARD',   'category'=>'admin',    'icon'=>'contact_page',    'color'=>'#ec4899', 'sla_days'=>5,  'sort_order'=>51, 'instructions'=>'Confirm your name, designation, phone, and email to appear on the card.'],
            ['name'=>'Office Supply Request',        'code'=>'ADMIN_SUPPLY', 'category'=>'admin',    'icon'=>'inventory_2',     'color'=>'#ec4899', 'sla_days'=>2,  'sort_order'=>52, 'instructions'=>'List the items needed with quantities.'],
        ];
        foreach ($requestTypes as $rt) {
            RequestType::firstOrCreate(['code' => $rt['code']], array_merge([
                'is_active' => true,
                'requires_attachment' => false,
                'requires_manager_approval' => false,
                'description' => null,
            ], $rt));
        }

    }
}
