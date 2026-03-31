<?php

namespace App\Modules\Contact\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Contact\Resources\ContactSubmissionResource;
use App\Modules\Contact\Services\ContactSubmissionService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ContactSubmissionController extends BaseController
{
    public function __construct(private ContactSubmissionService $contactSubmissionService) {}

    protected function respond($data = null, string $message = 'OK', int $code = 200)
    {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|string|email|max:190',
            'message' => 'required|string|min:10|max:3000',
            'sourcePage' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:0',
        ]);

        $result = $this->contactSubmissionService->submit([
            ...$data,
            'ipAddress' => $request->ip(),
            'userAgent' => (string) $request->userAgent(),
        ]);

        $responseData = [
            'submission' => new ContactSubmissionResource($result['submission']),
            'mailSent' => (bool) $result['mailSent'],
        ];

        $message = $result['mailSent']
            ? 'Contact submitted successfully'
            : 'Contact submitted. Email notification is not configured yet.';

        return $this->respond($responseData, $message, Response::HTTP_CREATED);
    }
}
