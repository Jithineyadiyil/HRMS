@extends('letters.layout')
@section('content')
<div class="ref-line">
  <span>Ref: {{ $ref }}</span>
  <span>Date: {{ $date }}</span>
</div>

@if($to_name)
<div class="to-block">
  <div class="to-label">To</div>
  <div class="to-name">{{ $to_name }}</div>
</div>
@else
<p>To Whom It May Concern,</p>
@endif

<h3 class="subject">EMPLOYMENT CERTIFICATE</h3>

<p>This is to certify that <strong>{{ $employee->first_name }} {{ $employee->last_name }}</strong>
bearing Iqama/ID No. <strong>{{ $employee->national_id ?? $employee->iqama_number ?? '—' }}</strong>
is currently employed with <strong>Diamond Insurance Broker</strong> in the capacity of
<strong>{{ $employee->designation?->title ?? '—' }}</strong> in the
<strong>{{ $employee->department?->name ?? '—' }}</strong> Department.</p>

<table class="data-table">
  <tr><td>Employee Name</td><td>{{ $employee->first_name }} {{ $employee->last_name }}</td></tr>
  <tr><td>Employee Code</td><td>{{ $employee->employee_code }}</td></tr>
  <tr><td>Position</td><td>{{ $employee->designation?->title ?? '—' }}</td></tr>
  <tr><td>Department</td><td>{{ $employee->department?->name ?? '—' }}</td></tr>
  <tr><td>Employment Since</td><td>{{ $hire_date }}</td></tr>
  <tr><td>Employment Type</td><td>{{ ucfirst(str_replace('_',' ', $employee->employment_type ?? 'Full Time')) }}</td></tr>
  <tr><td>Nationality</td><td>{{ $employee->nationality ?? '—' }}</td></tr>
</table>

<p>This certificate is issued as per his/her request for <strong>{{ $purpose ?: 'official' }}</strong> purposes and we wish him/her all the best.</p>

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
