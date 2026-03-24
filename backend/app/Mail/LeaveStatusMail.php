<?php
namespace App\Mail;
use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class LeaveStatusMail extends Mailable
{
    use Queueable;

    public function __construct(
        public LeaveRequest $leave,
        public string $action  // 'approved' | 'rejected' | 'submitted'
    ) {}

    public function envelope(): Envelope
    {
        $subjects = [
            'approved'  => 'Leave Request Approved – ' . $this->leave->reference,
            'rejected'  => 'Leave Request Rejected – ' . $this->leave->reference,
            'submitted' => 'New Leave Request – ' . $this->leave->reference,
        ];
        return new Envelope(subject: $subjects[$this->action] ?? 'Leave Update');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.leave-status');
    }
}
