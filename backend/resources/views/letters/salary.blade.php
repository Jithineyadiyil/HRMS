@extends('letters.layout')
@section('content')
<div class="ref-line">
  <span>Ref: {{ $ref }}</span>
  <span>Date: {{ $date }}</span>
</div>

<p>To Whom It May Concern,</p>

<h3 class="subject">SALARY CERTIFICATE</h3>

<p>This is to certify that <strong>{{ $employee->first_name }} {{ $employee->last_name }}</strong>
is a permanent employee of <strong>Diamond Insurance Broker</strong>, and has been employed with us
since <strong>{{ $hire_date }}</strong>.</p>

<p>His/Her current salary details are as follows:</p>

<table class="data-table">
  <tr><td>Employee Name</td><td>{{ $employee->first_name }} {{ $employee->last_name }}</td></tr>
  <tr><td>Employee Code</td><td>{{ $employee->employee_code }}</td></tr>
  <tr><td>Designation</td><td>{{ $employee->designation?->title ?? '—' }}</td></tr>
  <tr><td>Department</td><td>{{ $employee->department?->name ?? '—' }}</td></tr>
  <tr><td>Basic Salary</td><td>SAR {{ number_format($employee->salary ?? 0, 2) }}</td></tr>
  <tr><td>Housing Allowance</td><td>SAR {{ number_format($housing, 2) }}</td></tr>
  <tr><td>Transport Allowance</td><td>SAR {{ number_format($transport, 2) }}</td></tr>
  @if(($employee->mobile_allowance ?? 0) > 0)
  <tr><td>Mobile Allowance</td><td>SAR {{ number_format($employee->mobile_allowance, 2) }}</td></tr>
  @endif
  @if(($employee->food_allowance ?? 0) > 0)
  <tr><td>Food Allowance</td><td>SAR {{ number_format($employee->food_allowance, 2) }}</td></tr>
  @endif
  @if(($employee->other_allowances ?? 0) > 0)
  <tr><td>Other Allowances</td><td>SAR {{ number_format($employee->other_allowances, 2) }}</td></tr>
  @endif
  <tr><td><strong>Total Monthly Salary</strong></td><td><strong>SAR {{ number_format($gross, 2) }}</strong></td></tr>
  <tr><td>Nationality</td><td>{{ $employee->nationality ?? '—' }}</td></tr>
  <tr><td>Iqama / ID No.</td><td>{{ $employee->national_id ?? $employee->iqama_number ?? '—' }}</td></tr>
</table>

@if($purpose)
<p>This letter is issued as per his/her request for <strong>{{ $purpose }}</strong> purposes.</p>
@else
<p>This letter is issued as per his/her request.</p>
@endif

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
