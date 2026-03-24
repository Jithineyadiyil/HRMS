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

<h3 class="subject">EXPERIENCE LETTER</h3>

<p>This is to certify that <strong>{{ $employee->first_name }} {{ $employee->last_name }}</strong>
bearing Iqama/ID No. <strong>{{ $employee->national_id ?? $employee->iqama_number ?? '—' }}</strong>
has been employed with <strong>Diamond Insurance Broker</strong> from
<strong>{{ $hire_date }}</strong>
@if($end_date) to <strong>{{ $end_date }}</strong> @else to date @endif.</p>

<table class="data-table">
  <tr><td>Employee Name</td><td>{{ $employee->first_name }} {{ $employee->last_name }}</td></tr>
  <tr><td>Employee Code</td><td>{{ $employee->employee_code }}</td></tr>
  <tr><td>Position Held</td><td>{{ $employee->designation?->title ?? '—' }}</td></tr>
  <tr><td>Department</td><td>{{ $employee->department?->name ?? '—' }}</td></tr>
  <tr><td>Date of Joining</td><td>{{ $hire_date }}</td></tr>
  <tr><td>Last Working Day</td><td>{{ $end_date ?: 'Currently Employed' }}</td></tr>
  <tr><td>Total Experience</td><td>{{ $experience_years }}</td></tr>
</table>

<p>During his/her tenure, {{ $employee->first_name }} has proven to be a dedicated and hardworking professional.
We wish him/her continued success in future endeavors.</p>

<p>This letter is issued upon his/her request and without any liability on the part of the company.</p>

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
