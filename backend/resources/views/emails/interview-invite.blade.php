<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f0f4f8;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:30px 10px">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.1)">

  <tr><td style="background:#1e3a5f;padding:24px 32px">
    <p style="margin:0;color:#fff;font-size:20px;font-weight:bold">Diamond Insurance Broker</p>
    <p style="margin:4px 0 0;color:#93c5fd;font-size:12px;letter-spacing:1px;text-transform:uppercase">Recruitment Team</p>
  </td></tr>

  <tr><td style="background:#3b82f6;padding:14px 32px">
    <p style="margin:0;color:#fff;font-size:15px;font-weight:bold;text-transform:uppercase;letter-spacing:.5px">
      Interview Invitation
    </p>
  </td></tr>

  <tr><td style="padding:28px 32px">
    <p style="margin:0 0 16px;color:#1a1a2e;font-size:15px">
      Dear <strong>{{ $interview->application?->applicant_name ?? 'Candidate' }}</strong>,
    </p>
    <p style="color:#374151;font-size:14px;line-height:1.6;margin:0 0 20px">
      We are pleased to invite you for an interview for the position of
      <strong>{{ $interview->application?->jobPosting?->title ?? 'the applied position' }}</strong>
      at Diamond Insurance Broker.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:20px">
      <tr style="background:#f9fafb">
        <td style="padding:10px 16px;font-size:12px;color:#6b7280;font-weight:bold;text-transform:uppercase;width:35%">Date</td>
        <td style="padding:10px 16px;font-size:13px;color:#1a1a2e;font-weight:600">
          {{ \Carbon\Carbon::parse($interview->scheduled_at)->format('l, d F Y') }}
        </td>
      </tr>
      <tr>
        <td style="padding:10px 16px;font-size:12px;color:#6b7280;font-weight:bold;text-transform:uppercase;border-top:1px solid #e5e7eb">Time</td>
        <td style="padding:10px 16px;font-size:13px;color:#1a1a2e;border-top:1px solid #e5e7eb">
          {{ \Carbon\Carbon::parse($interview->scheduled_at)->format('h:i A') }}
        </td>
      </tr>
      <tr style="background:#f9fafb">
        <td style="padding:10px 16px;font-size:12px;color:#6b7280;font-weight:bold;text-transform:uppercase;border-top:1px solid #e5e7eb">Type</td>
        <td style="padding:10px 16px;font-size:13px;color:#1a1a2e;border-top:1px solid #e5e7eb">
          {{ ucfirst(str_replace('_',' ', $interview->type ?? 'In-person')) }}
        </td>
      </tr>
      @if($interview->location)
      <tr>
        <td style="padding:10px 16px;font-size:12px;color:#6b7280;font-weight:bold;text-transform:uppercase;border-top:1px solid #e5e7eb">Location</td>
        <td style="padding:10px 16px;font-size:13px;color:#1a1a2e;border-top:1px solid #e5e7eb">{{ $interview->location }}</td>
      </tr>
      @endif
      @if($interview->meeting_link)
      <tr style="background:#f9fafb">
        <td style="padding:10px 16px;font-size:12px;color:#6b7280;font-weight:bold;text-transform:uppercase;border-top:1px solid #e5e7eb">Meeting Link</td>
        <td style="padding:10px 16px;font-size:13px;border-top:1px solid #e5e7eb">
          <a href="{{ $interview->meeting_link }}" style="color:#3b82f6">{{ $interview->meeting_link }}</a>
        </td>
      </tr>
      @endif
    </table>

    @if($interview->notes)
    <div style="background:#eff6ff;border-left:4px solid #3b82f6;padding:12px 16px;margin-bottom:20px;border-radius:0 8px 8px 0">
      <p style="margin:0;font-size:13px;color:#374151"><strong>Notes from HR:</strong> {{ $interview->notes }}</p>
    </div>
    @endif

    <p style="color:#374151;font-size:14px;line-height:1.6;margin:0 0 16px">
      Please confirm your availability by replying to this email. If you need to reschedule, contact us at least 24 hours in advance.
    </p>
    <p style="color:#6b7280;font-size:13px;margin:0">
      Contact HR: <a href="mailto:hr@diamond-insurance.com.sa" style="color:#1e3a5f">hr@diamond-insurance.com.sa</a>
    </p>
  </td></tr>

  <tr><td style="background:#f9fafb;padding:16px 32px;border-top:1px solid #e5e7eb;text-align:center">
    <p style="margin:0;color:#9ca3af;font-size:11px">Diamond Insurance Broker &bull; Riyadh, Saudi Arabia &bull; Confidential</p>
  </td></tr>
</table>
</td></tr></table>
</body>
</html>
