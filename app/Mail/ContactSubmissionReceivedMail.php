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
        $isAnonymous = $this->submission->email === 'anonymous@noreply.local';

        $subject = $isAnonymous
            ? '[Cineon] Phản ánh chất lượng mới'
            : '[Cineon] Liên hệ mới từ ' . $this->submission->name;

        return $this->subject($subject)
            ->view('emails.contact.submission-received', [
                'submission'   => $this->submission,
                'isAnonymous'  => $isAnonymous,
            ]);
    }
}
