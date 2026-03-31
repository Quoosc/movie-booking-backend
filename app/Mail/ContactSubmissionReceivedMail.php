<?php

namespace App\Mail;

use App\Models\ContactSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactSubmissionReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ContactSubmission $submission) {}

    public function build()
    {
        $subject = '[Movie Booking] New contact from ' . $this->submission->name;

        return $this->subject($subject)
            ->view('emails.contact.submission-received', [
                'submission' => $this->submission,
            ]);
    }
}
