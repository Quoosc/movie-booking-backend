<?php

namespace App\Modules\Contact\Services;

use App\Mail\ContactSubmissionReceivedMail;
use App\Models\ContactSubmission;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ContactSubmissionService
{
    public function submit(array $data): array
    {
        $submission = ContactSubmission::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'message' => $data['message'],
            'source_page' => $data['sourcePage'] ?? null,
            'ip_address' => $data['ipAddress'] ?? null,
            'user_agent' => $data['userAgent'] ?? null,
        ]);

        $mailSent = false;
        $receiverEmail = (string) config('contact.receiver_email');
        $receiverName = (string) config('contact.receiver_name');
        $notifyEnabled = (bool) config('contact.notify_enabled', true);
        $mailDriver = (string) config('mail.default', 'log');
        $isDeliveryMailer = !in_array($mailDriver, ['log', 'array'], true);

        if ($notifyEnabled && $receiverEmail !== '') {
            try {
                $mail = Mail::to($receiverEmail, $receiverName !== '' ? $receiverName : null);
                $mail->send(new ContactSubmissionReceivedMail($submission));

                if ($isDeliveryMailer) {
                    $submission->notified_at = now();
                    $submission->save();
                    $mailSent = true;
                }
            } catch (Throwable $e) {
                Log::error('Contact submission notification failed', [
                    'contact_submission_id' => $submission->contact_submission_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'submission' => $submission->refresh(),
            'mailSent' => $mailSent,
        ];
    }
}
