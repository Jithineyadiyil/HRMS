<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  * { margin:0; padding:0; box-sizing:border-box; font-family: Arial, sans-serif; }
  body { font-size:9pt; color:#1a1a2e; background:#fff; }

  /* Header */
  .pdf-header { background:#1e3a5f; color:#fff; padding:16px 20px; display:flex; justify-content:space-between; align-items:center; }
  .pdf-logo   { font-size:14pt; font-weight:bold; letter-spacing:0.5px; }
  .pdf-company{ font-size:8pt; opacity:0.75; margin-top:2px; }
  .pdf-title  { font-size:16pt; font-weight:bold; text-align:right; }

  /* Filter summary */
  .filters    { background:#f0f4ff; border:1px solid #d0d8f0; padding:8px 16px; margin:12px 0; display:flex; flex-wrap:wrap; gap:8px 24px; }
  .filter-item{ font-size:8pt; color:#444; }
  .filter-item strong { color:#1e3a5f; }

  /* Table */
  table   { width:100%; border-collapse:collapse; margin-top:8px; }
  thead tr{ background:#1e3a5f; color:#fff; }
  th      { padding:7px 8px; text-align:left; font-size:7.5pt; font-weight:600; white-space:nowrap; }
  tbody tr{ border-bottom:1px solid #e8ecf4; }
  tbody tr:nth-child(even){ background:#f7f9ff; }
  td      { padding:6px 8px; font-size:8pt; vertical-align:top; }

  /* Status badges */
  .badge  { display:inline-block; padding:1px 7px; border-radius:4px; font-size:7pt; font-weight:600; }
  .badge-active,.badge-open,.badge-approved,.badge-present   { background:#e0f7ec; color:#0a7c46; }
  .badge-pending,.badge-on_hold,.badge-late                  { background:#fff5e0; color:#a05c00; }
  .badge-rejected,.badge-closed,.badge-absent,.badge-terminated{ background:#fde8e8; color:#a00f0f; }
  .badge-draft,.badge-cancelled,.badge-inactive              { background:#f0f0f0; color:#555; }
  .badge-hired,.badge-settled,.badge-finalized               { background:#e5eeff; color:#1a3ea0; }

  /* Footer */
  .pdf-footer { position:fixed; bottom:0; left:0; right:0; text-align:center; font-size:7pt; color:#999; border-top:1px solid #ddd; padding:6px; background:#fff; }
  .pdf-footer span { margin:0 8px; }

  /* Totals row */
  .totals-row td { font-weight:bold; background:#e8ecf4; border-top:2px solid #1e3a5f; }
</style>
</head>
<body>

<div class="pdf-header">
  <div>
    <div class="pdf-logo">Diamond Insurance Broker</div>
    <div class="pdf-company">Human Resources Management System</div>
  </div>
  <div class="pdf-title">{{ $title }}</div>
</div>

<div class="filters">
  @foreach($filters as $label => $value)
    <div class="filter-item"><strong>{{ $label }}:</strong> {{ $value }}</div>
  @endforeach
  <div class="filter-item"><strong>Total Records:</strong> {{ count($data) }}</div>
</div>

@if(count($data) > 0)
<table>
  <thead>
    <tr>
      @foreach($headers as $h)
        <th>{{ $h }}</th>
      @endforeach
    </tr>
  </thead>
  <tbody>
    @foreach($data as $row)
    <tr>
      @foreach(array_values((array)$row) as $val)
        <td>
          @php
            $lower = strtolower((string)$val);
            $isBadge = in_array($lower, ['active','inactive','pending','approved','rejected','cancelled','present','absent','late','open','closed','on_hold','draft','hired','settled','finalized','terminated']);
          @endphp
          @if($isBadge)
            <span class="badge badge-{{ str_replace(' ','_',$lower) }}">{{ $val }}</span>
          @else
            {{ $val }}
          @endif
        </td>
      @endforeach
    </tr>
    @endforeach
  </tbody>
</table>
@else
  <p style="text-align:center;padding:40px;color:#999">No records found for the selected filters.</p>
@endif

<div class="pdf-footer">
  <span>Diamond Insurance Broker — HRMS</span>
  <span>|</span>
  <span>{{ $title }}</span>
  <span>|</span>
  <span>Generated: {{ now()->format('d M Y H:i') }}</span>
  <span>|</span>
  <span>Confidential</span>
</div>

</body>
</html>
