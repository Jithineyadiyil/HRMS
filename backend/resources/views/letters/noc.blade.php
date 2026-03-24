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

<h3 class="subject">NO OBJECTION CERTIFICATE (NOC)</h3>

<p>This is to certify that <strong>{{ $employee->first_name }} {{ $employee->last_name }}</strong>,
{{ $employee->designation?->title ?? 'Employee' }} in our {{ $employee->department?->name ?? '' }} Department,
bearing Iqama/ID No. <strong>{{ $employee->national_id ?? $employee->iqama_number ?? '—' }}</strong>,
has been employed with Diamond Insurance Broker since <strong>{{ $hire_date }}</strong>.</p>

<table class="data-table">
  <tr><td>Employee Name</td><td>{{ $employee->first_name }} {{ $employee->last_name }}</td></tr>
  <tr><td>Employee Code</td><td>{{ $employee->employee_code }}</td></tr>
  <tr><td>Position</td><td>{{ $employee->designation?->title ?? '—' }}</td></tr>
  <tr><td>Nationality</td><td>{{ $employee->nationality ?? '—' }}</td></tr>
  <tr><td>Iqama / ID No.</td><td>{{ $employee->national_id ?? $employee->iqama_number ?? '—' }}</td></tr>
</table>

<p>We, <strong>Diamond Insurance Broker</strong>, have <strong>no objection</strong> to
{{ $employee->first_name }} {{ $employee->last_name }}
@if($purpose) {{ $purpose }}. @else proceeding with his/her personal matter. @endif</p>

<p>This NOC is issued in good faith and without any responsibility or liability on our part.</p>

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
