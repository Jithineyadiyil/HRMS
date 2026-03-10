import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { Router, ActivatedRoute } from '@angular/router';
import { HttpClient } from '@angular/common/http';

@Component({
  standalone: false,
  selector: 'app-employee-form',
  templateUrl: './employee-form.component.html',
  styleUrls: ['./employee-form.component.scss'],
})
export class EmployeeFormComponent implements OnInit {
  form!: FormGroup;
  isEdit        = false;
  employeeId: any = null;
  saving        = false;
  loadingData   = false;
  departments:   any[] = [];
  designations:  any[] = [];
  managers:      any[] = [];
  activeTab     = 'personal';
  errorMsg      = '';

  tabs = [
    { id: 'personal',   label: 'Personal Info',  icon: 'person' },
    { id: 'employment', label: 'Employment',      icon: 'work' },
    { id: 'financial',  label: 'Financial',       icon: 'account_balance_wallet' },
    { id: 'emergency',  label: 'Emergency',       icon: 'emergency' },
  ];

  constructor(
    private fb: FormBuilder,
    private http: HttpClient,
    private router: Router,
    private route: ActivatedRoute
  ) {}

  ngOnInit() {
    this.buildForm();
    this.loadLookups();

    this.employeeId = this.route.snapshot.paramMap.get('id');
    this.isEdit = !!this.employeeId && this.employeeId !== 'new';

    if (this.isEdit) {
      this.loadingData = true;
      this.http.get<any>(`/api/v1/employees/${this.employeeId}`).subscribe({
        next: r => {
          this.loadingData = false;
          const e = r.employee || r;
          this.form.patchValue({
            prefix: e.prefix, first_name: e.first_name, last_name: e.last_name,
            arabic_name: e.arabic_name, email: e.email, phone: e.phone,
            work_phone: e.work_phone, extension: e.extension,
            dob: e.dob, gender: e.gender, marital_status: e.marital_status,
            nationality: e.nationality, national_id: e.national_id,
            address: e.address, city: e.city, country: e.country,
            department_id: e.department_id, designation_id: e.designation_id,
            manager_id: e.manager_id, employment_type: e.employment_type,
            mode_of_employment: e.mode_of_employment, role: e.role,
            status: e.status, hire_date: e.hire_date,
            confirmation_date: e.confirmation_date, termination_date: e.termination_date,
            probation_period: e.probation_period, years_of_experience: e.years_of_experience,
            salary: e.salary, bank_name: e.bank_name, bank_account: e.bank_account,
            emergency_contact_name: e.emergency_contact_name,
            emergency_contact_phone: e.emergency_contact_phone,
            notes: e.notes,
          });
          if (e.department_id) this.loadDesignations(e.department_id);
        },
        error: () => { this.loadingData = false; }
      });
    }
  }

  buildForm() {
    this.form = this.fb.group({
      // Personal
      prefix:          [''],
      first_name:      ['', [Validators.required, Validators.maxLength(100)]],
      last_name:       ['', [Validators.required, Validators.maxLength(100)]],
      arabic_name:     [''],
      email:           ['', [Validators.required, Validators.email]],
      phone:           [''],
      work_phone:      [''],
      extension:       [''],
      dob:             [''],
      gender:          [''],
      marital_status:  [''],
      nationality:     [''],
      national_id:     [''],
      address:         [''],
      city:            [''],
      country:         [''],
      // Employment
      department_id:      [''],
      designation_id:     [''],
      manager_id:         [''],
      employment_type:    ['full_time', Validators.required],
      mode_of_employment: ['direct'],
      role:               ['employee'],
      status:             ['active', Validators.required],
      hire_date:          ['', Validators.required],
      confirmation_date:  [''],
      termination_date:   [''],
      probation_period:   [0],
      years_of_experience:[''],
      // Financial
      salary:          ['', [Validators.required, Validators.min(0)]],
      bank_name:       [''],
      bank_account:    [''],
      // Emergency
      emergency_contact_name:     [''],
      emergency_contact_phone:    [''],
      emergency_contact_relation: [''],
      notes:                   [''],
    });
  }

  loadLookups() {
    this.http.get<any>('/api/v1/departments').subscribe(r => this.departments = r?.data || r || []);
    this.http.get<any>('/api/v1/employees?status=active&per_page=100').subscribe(r => this.managers = r?.data || []);
  }

  onDeptChange(e: any) {
    const id = e.target.value;
    this.form.patchValue({ designation_id: '' });
    if (id) this.loadDesignations(id);
    else this.designations = [];
  }

  loadDesignations(deptId: any) {
    this.http.get<any>(`/api/v1/designations?department_id=${deptId}`).subscribe(r => this.designations = r?.data || r || []);
  }

  submit() {
    if (this.form.invalid) { this.form.markAllAsTouched(); this.errorMsg = 'Please fill in all required fields.'; return; }
    this.saving   = true;
    this.errorMsg = '';
    const url  = this.isEdit ? `/api/v1/employees/${this.employeeId}` : '/api/v1/employees';
    const req  = this.isEdit ? this.http.put<any>(url, this.form.value) : this.http.post<any>(url, this.form.value);
    req.subscribe({
      next: r => {
        const id = r.employee?.id || this.employeeId;
        this.router.navigate(['/employees', id]);
      },
      error: err => {
        this.saving   = false;
        this.errorMsg = err?.error?.message || 'Failed to save employee.';
      }
    });
  }

  cancel() {
    this.isEdit ? this.router.navigate(['/employees', this.employeeId]) : this.router.navigate(['/employees']);
  }

  f(name: string) { return this.form.get(name); }
  err(name: string) { const c = this.f(name); return c?.invalid && c?.touched ? c.errors : null; }

  prevTab() {
    const i = this.tabs.findIndex(t => t.id === this.activeTab);
    if (i > 0) this.activeTab = this.tabs[i - 1].id;
  }

  nextTab() {
    const i = this.tabs.findIndex(t => t.id === this.activeTab);
    if (i < this.tabs.length - 1) this.activeTab = this.tabs[i + 1].id;
  }
}
