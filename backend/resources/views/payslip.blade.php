<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>Payslip – {{ $payslip->employee->employee_code ?? '' }} – {{ $payslip->payroll->month ?? '' }}</title>
<style>
  @page { size: A4; margin: 0; }
  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'DejaVu Sans', 'Arial', sans-serif;
    font-size: 11px;
    color: #1a1a2e;
    background: #ffffff;
  }

  .page {
    width: 210mm;
    min-height: 297mm;
    background: #fff;
    position: relative;
    padding-bottom: 70px; /* reserve space for fixed footer */
  }

  /* ══ HEADER ══════════════════════════════════════════════════════════ */
  .header {
    background: #1a1a2e;
    padding: 20px 30px 16px;
  }
  .header-table  { width: 100%; border-collapse: collapse; }
  .header-left   { vertical-align: middle; width: 55%; }
  .header-right  { vertical-align: middle; text-align: right; }

  .logo-img {
    height: 50px;
    width: auto;
    display: block;
  }
  .company-sub {
    color: #19a8c7;
    font-size: 8.5px;
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-top: 5px;
  }

  .doc-title   { color: #ffffff; font-size: 22px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; }
  .doc-period  { color: #19a8c7; font-size: 11px; margin-top: 4px; font-weight: bold; }
  .doc-dates   { color: #8899aa; font-size: 9px; margin-top: 3px; }

  /* ══ ACCENT BAR ══════════════════════════════════════════════════════ */
  .accent-bar { height: 4px; background: #19a8c7; }

  /* ══ EMPLOYEE BAND ═══════════════════════════════════════════════════ */
  .emp-band {
    background: #f4f7fb;
    padding: 14px 30px;
    border-bottom: 1px solid #dde3ef;
  }
  .emp-table { width: 100%; border-collapse: collapse; }
  .emp-cell  { vertical-align: top; padding-right: 10px; }
  .emp-lbl {
    font-size: 8px;
    color: #19a8c7;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    font-weight: bold;
    margin-bottom: 3px;
  }
  .emp-val  { font-size: 13px; font-weight: bold; color: #1a1a2e; }
  .emp-val2 { font-size: 9.5px; color: #556; margin-top: 2px; }

  /* ══ NET HERO ════════════════════════════════════════════════════════ */
  .net-hero { background: #19a8c7; padding: 16px 30px; }
  .net-table { width: 100%; border-collapse: collapse; }
  .net-left  { vertical-align: middle; }
  .net-right { vertical-align: middle; text-align: right; }
  .net-lbl   { color: rgba(255,255,255,0.75); font-size: 9px; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 3px; }
  .net-val   { color: #fff; font-size: 30px; font-weight: bold; line-height: 1; }
  .net-sub   { color: rgba(255,255,255,0.70); font-size: 10px; margin-top: 5px; }

  .chip {
    display: inline-block;
    padding: 3px 9px;
    border-radius: 4px;
    font-size: 9.5px;
    font-weight: bold;
    color: #fff;
    margin-left: 5px;
  }
  .chip-blue   { background: rgba(255,255,255,0.22); }
  .chip-red    { background: rgba(220,50,50,0.70); }
  .chip-orange { background: rgba(220,130,0,0.70); }

  /* ══ BREAKDOWN ═══════════════════════════════════════════════════════ */
  .breakdown-wrap { padding: 18px 30px 10px; }
  .bd-table { width: 100%; border-collapse: collapse; }
  .bd-earn   { vertical-align: top; width: 50%; padding-right: 14px; }
  .bd-deduct { vertical-align: top; width: 50%; padding-left: 14px; }

  .sec-title {
    font-size: 9.5px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 7px 11px;
    border-radius: 4px;
    margin-bottom: 7px;
  }
  .sec-earn   { background: #e8f7f0; color: #1a7a50; border-left: 3px solid #10b981; }
  .sec-deduct { background: #fef2f2; color: #991b1b; border-left: 3px solid #ef4444; }

  .row { width: 100%; border-collapse: collapse; margin-bottom: 1px; }
  .row-lbl  { font-size: 10.5px; color: #333; padding: 5px 0 4px; vertical-align: top; }
  .row-hint { font-size: 8px; color: #999; display: block; margin-top: 1px; }
  .row-val  { font-size: 10.5px; font-weight: bold; text-align: right; padding: 5px 0 4px; vertical-align: top; }
  .row-sep  { border-bottom: 1px dashed #e2e6ef; }
  .earn-v   { color: #1a7a50; }
  .deduct-v { color: #991b1b; }
  .info-v   { color: #777; }

  .sec-total {
    width: 100%;
    border-collapse: collapse;
    margin-top: 7px;
    border-radius: 4px;
  }
  .st-row { border-radius: 4px; }
  .st-lbl { font-size: 11px; font-weight: bold; padding: 8px 11px; }
  .st-val { font-size: 11px; font-weight: bold; text-align: right; padding: 8px 11px; }
  .tot-earn   { background: #e8f7f0; color: #1a7a50; }
  .tot-deduct { background: #fef2f2; color: #991b1b; }

  /* ══ GOSI INFO BOX ═══════════════════════════════════════════════════ */
  .gosi-box {
    background: #eff4ff;
    border: 1px solid #c5d4f0;
    border-radius: 5px;
    padding: 8px 11px;
    margin-top: 10px;
    font-size: 9px;
    color: #2d4a8a;
    line-height: 1.6;
  }

  /* ══ SUMMARY BAR ═════════════════════════════════════════════════════ */
  .summary-wrap { padding: 0 30px 18px; }
  .summary-bar {
    background: #1a1a2e;
    border-radius: 8px;
    padding: 14px 20px;
  }
  .sm-table { width: 100%; border-collapse: collapse; }
  .sm-cell  { text-align: center; vertical-align: middle; }
  .sm-sep   { width: 1px; background: rgba(255,255,255,0.15); }
  .sm-lbl   { color: rgba(255,255,255,0.55); font-size: 8.5px; text-transform: uppercase; letter-spacing: 1px; }
  .sm-val   { color: #fff; font-size: 15px; font-weight: bold; padding-top: 3px; }
  .sm-hl    { color: #19a8c7; font-size: 18px; }

  /* ══ FOOTER ══════════════════════════════════════════════════════════ */
  .footer {
    position: fixed;
    bottom: 0;
    left: 0; right: 0;
    padding: 10px 30px;
    background: #f4f7fb;
    border-top: 3px solid #19a8c7;
  }
  .ft-table { width: 100%; border-collapse: collapse; }
  .ft-left  { vertical-align: middle; font-size: 9px; color: #555; line-height: 1.6; }
  .ft-center { vertical-align: bottom; text-align: center; font-size: 8.5px; color: #888; }
  .ft-right { vertical-align: middle; text-align: right; font-size: 9px; color: #777; line-height: 1.6; }
  .sig-line { border-top: 1px solid #bbb; width: 110px; margin: 18px auto 4px; }
  .confidential {
    display: inline-block;
    background: #fff8e1;
    border: 1px solid #e8cf80;
    border-radius: 3px;
    padding: 2px 7px;
    font-size: 7.5px;
    color: #7a5c00;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-top: 3px;
  }

  .na-text { font-size: 9px; color: #aaa; font-style: italic; }
</style>
</head>
<body>
<div class="page">

  <!-- ══ HEADER ══ -->
  <div class="header">
    <table class="header-table">
      <tr>
        <td class="header-left">
          {{-- file:// path required by DomPDF to load local images --}}
          <img src="file://{{ public_path('diamond-logo.png') }}" class="logo-img" alt="Diamond Insurance Broker">
          <div class="company-sub">Human Resources Department</div>
        </td>
        <td class="header-right">
          <div class="doc-title">Pay Slip</div>
          <div class="doc-period">{{ \Carbon\Carbon::parse($payslip->payroll->month . '-01')->format('F Y') }}</div>
          <div class="doc-dates">
            Period: {{ \Carbon\Carbon::parse($payslip->payroll->period_start)->format('d M Y') }}
            &ndash; {{ \Carbon\Carbon::parse($payslip->payroll->period_end)->format('d M Y') }}
          </div>
        </td>
      </tr>
    </table>
  </div>
  <div class="accent-bar"></div>

  <!-- ══ EMPLOYEE BAND ══ -->
  <div class="emp-band">
    <table class="emp-table">
      <tr>
        <td class="emp-cell">
          <div class="emp-lbl">Employee Name</div>
          <div class="emp-val">{{ $payslip->employee->first_name }} {{ $payslip->employee->last_name }}</div>
          <div class="emp-val2">{{ $payslip->employee->designation?->title ?? '—' }}</div>
        </td>
        <td class="emp-cell">
          <div class="emp-lbl">Employee ID</div>
          <div class="emp-val">{{ $payslip->employee->employee_code }}</div>
          <div class="emp-val2">{{ $payslip->employee->department?->name ?? '—' }}</div>
        </td>
        <td class="emp-cell">
          <div class="emp-lbl">Nationality</div>
          <div class="emp-val" style="color:{{ $payslip->is_saudi ? '#1a7a50' : '#2563eb' }}">
            {{ $payslip->is_saudi ? 'Saudi National' : 'Non-Saudi Expatriate' }}
          </div>
          <div class="emp-val2">Pay Date: {{ \Carbon\Carbon::parse($payslip->payroll->period_end)->format('d M Y') }}</div>
        </td>
        <td class="emp-cell" style="padding-right:0">
          <div class="emp-lbl">Reference No.</div>
          <div class="emp-val">{{ 'PAY-' . str_replace('-', '', $payslip->payroll->month) . '-' . str_pad($payslip->employee_id, 4, '0', STR_PAD_LEFT) }}</div>
          <div class="emp-val2">Generated: {{ now()->format('d M Y') }}</div>
        </td>
      </tr>
    </table>
  </div>

  <!-- ══ NET HERO ══ -->
  <div class="net-hero">
    <table class="net-table">
      <tr>
        <td class="net-left">
          <div class="net-lbl">Net Salary Payable</div>
          <div class="net-val">SAR {{ number_format($payslip->net_salary, 2) }}</div>
          <div class="net-sub">
            Gross SAR {{ number_format($payslip->gross_salary, 2) }}
            &nbsp;&minus;&nbsp;
            Deductions SAR {{ number_format($payslip->total_deductions, 2) }}
          </div>
        </td>
        <td class="net-right">
          <div class="net-lbl" style="margin-bottom:7px">Attendance Summary</div>
          <span class="chip chip-blue">{{ $payslip->working_days ?? 0 }} Days Worked</span>
          @if(($payslip->absent_days ?? 0) > 0)
            <span class="chip chip-red">{{ $payslip->absent_days }} Absent</span>
          @endif
          @if(($payslip->leave_days ?? 0) > 0)
            <span class="chip chip-orange">{{ $payslip->leave_days }} Leave</span>
          @endif
        </td>
      </tr>
    </table>
  </div>

  <!-- ══ BREAKDOWN ══ -->
  <div class="breakdown-wrap">
    <table class="bd-table">
      <tr>

        {{-- EARNINGS --}}
        <td class="bd-earn">
          <div class="sec-title sec-earn">&#9650; Earnings</div>

          <table class="row row-sep" style="margin-bottom:0"><tr>
            <td class="row-lbl">Basic Salary</td>
            <td class="row-val earn-v">SAR {{ number_format($payslip->basic_salary, 2) }}</td>
          </tr></table>

          <table class="row row-sep" style="margin-bottom:0"><tr>
            <td class="row-lbl">
              Housing Allowance
              <span class="row-hint">25% of basic salary</span>
            </td>
            <td class="row-val earn-v">SAR {{ number_format($payslip->housing_allowance, 2) }}</td>
          </tr></table>

          <table class="row row-sep" style="margin-bottom:0"><tr>
            <td class="row-lbl">
              Transport Allowance
              <span class="row-hint">Fixed monthly allowance</span>
            </td>
            <td class="row-val earn-v">SAR {{ number_format($payslip->transport_allowance, 2) }}</td>
          </tr></table>

          @if(($payslip->other_allowances ?? 0) > 0)
          <table class="row row-sep" style="margin-bottom:0"><tr>
            <td class="row-lbl">Other Allowances</td>
            <td class="row-val earn-v">SAR {{ number_format($payslip->other_allowances, 2) }}</td>
          </tr></table>
          @endif

          <table class="sec-total"><tr class="st-row tot-earn">
            <td class="st-lbl tot-earn">Total Earnings</td>
            <td class="st-val tot-earn">SAR {{ number_format($payslip->total_earnings ?? $payslip->gross_salary, 2) }}</td>
          </tr></table>
        </td>

        {{-- DEDUCTIONS --}}
        <td class="bd-deduct">
          <div class="sec-title sec-deduct">&#9660; Deductions</div>

          @if($payslip->is_saudi)
            <table class="row row-sep" style="margin-bottom:0"><tr>
              <td class="row-lbl">
                GOSI &mdash; Employee Share
                <span class="row-hint">9% of basic salary (deducted)</span>
              </td>
              <td class="row-val deduct-v">SAR {{ number_format($payslip->gosi_employee, 2) }}</td>
            </tr></table>

            <table class="row row-sep" style="margin-bottom:0"><tr>
              <td class="row-lbl">
                GOSI &mdash; Employer Contribution
                <span class="row-hint">11.75% of basic (company cost, not deducted)</span>
              </td>
              <td class="row-val info-v">SAR {{ number_format($payslip->gosi_employer, 2) }}</td>
            </tr></table>
          @else
            <table class="row row-sep" style="margin-bottom:0"><tr>
              <td class="row-lbl na-text">GOSI not applicable &mdash; Non-Saudi employee</td>
              <td class="row-val info-v">SAR 0.00</td>
            </tr></table>
          @endif

          @if(($payslip->other_deductions ?? 0) > 0)
          <table class="row row-sep" style="margin-bottom:0"><tr>
            <td class="row-lbl">Other Deductions</td>
            <td class="row-val deduct-v">SAR {{ number_format($payslip->other_deductions, 2) }}</td>
          </tr></table>
          @endif

          <table class="sec-total"><tr class="st-row tot-deduct">
            <td class="st-lbl tot-deduct">Total Deductions</td>
            <td class="st-val tot-deduct">SAR {{ number_format($payslip->total_deductions, 2) }}</td>
          </tr></table>

          @if($payslip->is_saudi)
          <div class="gosi-box">
            <strong>GOSI Note:</strong> Employee contribution of
            <strong>SAR {{ number_format($payslip->gosi_employee, 2) }}</strong> (9%) is deducted from salary.
            Employer contributes <strong>SAR {{ number_format($payslip->gosi_employer, 2) }}</strong> (11.75%)
            directly to GOSI &mdash; this is <em>not</em> deducted from your pay.
          </div>
          @endif
        </td>

      </tr>
    </table>
  </div>

  <!-- ══ SUMMARY BAR ══ -->
  <div class="summary-wrap">
    <div class="summary-bar">
      <table class="sm-table">
        <tr>
          <td class="sm-cell">
            <div class="sm-lbl">Gross Salary</div>
            <div class="sm-val">SAR {{ number_format($payslip->gross_salary, 2) }}</div>
          </td>
          <td class="sm-sep">&nbsp;</td>
          <td class="sm-cell">
            <div class="sm-lbl">Total Deductions</div>
            <div class="sm-val" style="color:#f87171">SAR {{ number_format($payslip->total_deductions, 2) }}</div>
          </td>
          <td class="sm-sep">&nbsp;</td>
          <td class="sm-cell">
            <div class="sm-lbl">Net Salary Payable</div>
            <div class="sm-val sm-hl">SAR {{ number_format($payslip->net_salary, 2) }}</div>
          </td>
          <td class="sm-sep">&nbsp;</td>
          <td class="sm-cell">
            <div class="sm-lbl">Days Worked</div>
            <div class="sm-val">{{ $payslip->working_days ?? 0 }} / 30</div>
          </td>
        </tr>
      </table>
    </div>
  </div>

  <!-- ══ FOOTER (fixed at page bottom) ══ -->
  <div class="footer">
    <table class="ft-table">
      <tr>
        <td class="ft-left" style="width:40%">
          <strong>Diamond Insurance Broker</strong><br>
          Human Resources Department<br>
          <span class="confidential">Confidential &mdash; Employee Copy</span>
        </td>
        <td class="ft-center" style="width:25%">
          <div class="sig-line"></div>
          Authorized HR Signature
        </td>
        <td class="ft-right" style="width:35%">
          Ref: PAY-{{ str_replace('-', '', $payslip->payroll->month) }}-{{ str_pad($payslip->employee_id, 4, '0', STR_PAD_LEFT) }}<br>
          Employee ID: {{ $payslip->employee->employee_code }}<br>
          Issued: {{ now()->format('d M Y, H:i') }}
        </td>
      </tr>
    </table>
  </div>

</div>
</body>
</html>
