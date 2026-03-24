@extends('letters.layout')
@section('content')
<div class="ref-line">
  <span>Ref: {{ $ref }}</span>
  <span>Date: {{ $date }}</span>
</div>

<div class="to-block">
  <div class="to-label">To</div>
  <div class="to-name">The Branch Manager</div>
  <div class="to-title">{{ $bank_name ?: 'The Bank' }}</div>
</div>

<h3 class="subject">SALARY TRANSFER LETTER / BANK CONFIRMATION</h3>

<p>Dear Sir/Madam,</p>

<p>We are pleased to introduce <strong>{{ $employee->first_name }} {{ $employee->last_name }}</strong>,
{{ $employee->designation?->title ?? 'Employee' }}, who is a permanent employee of
<strong>Diamond Insurance Broker</strong>. His/Her salary is transferred monthly through our payroll system.</p>

<table class="data-table">
  <tr><td>Employee Name</td><td>{{ $employee->first_name }} {{ $employee->last_name }}</td></tr>
  <tr><td>Employee Code</td><td>{{ $employee->employee_code }}</td></tr>
  <tr><td>Designation</td><td>{{ $employee->designation?->title ?? '—' }}</td></tr>
  <tr><td>Department</td><td>{{ $employee->department?->name ?? '—' }}</td></tr>
  <tr><td>Monthly Salary</td><td>SAR {{ number_format($gross, 2) }}</td></tr>
  <tr><td>Date of Joining</td><td>{{ $hire_date }}</td></tr>
  <tr><td>Iqama / ID No.</td><td>{{ $employee->national_id ?? $employee->iqama_number ?? '—' }}</td></tr>
</table>

<p>We request you to extend all banking facilities to our employee as you deem appropriate.</p>

<div class="signature-block">
  <div class="sig-line"></div>
  <div class="sig-name">HR Manager</div>
  <div class="sig-title">Human Resources Department</div>
  <div class="sig-company">Diamond Insurance Broker</div>
</div>

<div class="stamp-area">
  <div class="stamp-box">Official Stamp</div>
</div>
@endsection
