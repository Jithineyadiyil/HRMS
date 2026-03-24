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

<h3 class="subject">SALARY TRANSFER INSTRUCTION</h3>

<p>Dear Sir/Madam,</p>

<p>We request you to transfer the monthly salary of our employee mentioned below to the new account details provided, effective from the next payroll cycle.</p>

<table class="data-table">
  <tr><td>Employee Name</td><td>{{ $employee->first_name }} {{ $employee->last_name }}</td></tr>
  <tr><td>Employee Code</td><td>{{ $employee->employee_code }}</td></tr>
  <tr><td>Monthly Salary</td><td>SAR {{ number_format($gross, 2) }}</td></tr>
  <tr><td>New Bank Name</td><td>{{ $bank_name ?: '—' }}</td></tr>
  @if($account_no)
  <tr><td>Account Number</td><td>{{ $account_no }}</td></tr>
  @endif
  <tr><td>Iqama / ID No.</td><td>{{ $employee->national_id ?? $employee->iqama_number ?? '—' }}</td></tr>
</table>

<p>Please proceed accordingly and confirm receipt of this instruction.</p>

<div class="signature-block">
  <div class="sig-line"></div>
  <div class="sig-name">Finance Manager</div>
  <div class="sig-title">Finance Department</div>
  <div class="sig-company">Diamond Insurance Broker</div>
</div>

<div class="stamp-area">
  <div class="stamp-box">Official Stamp</div>
</div>
@endsection
