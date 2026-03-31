<?php

namespace App\Modules\Contact\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ContactSubmissionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'contactSubmissionId' => $this->contact_submission_id,
            'name' => $this->name,
            'email' => $this->email,
            'message' => $this->message,
            'sourcePage' => $this->source_page,
            'notifiedAt' => $this->notified_at,
            'createdAt' => $this->created_at,
        ];
    }
}
