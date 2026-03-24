<?php
namespace App\Mail;

use App\Models\Interview;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InterviewInviteMail extends Mailable
{
    use Queueable;

    public function __construct(public Interview $interview) {}

    public function envelope(): Envelope
    {
        $posting = $this->interview->application?->jobPosting;
        return new Envelope(
            subject: 'Interview Invitation — ' . ($posting?->title ?? 'Position at Diamond Insurance Broker')
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.interview-invite');
    }
}
