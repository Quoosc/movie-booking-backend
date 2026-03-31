<?php

namespace App\Modules\HeroSlide\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\HeroSlide\Resources\HeroSlideResource;
use App\Modules\HeroSlide\Services\HeroSlideService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class HeroSlideController extends BaseController
{
    public function __construct(private HeroSlideService $heroSlideService) {}

    protected function respond($data = null, string $message = 'OK', int $code = 200)
    {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function ensureAdmin()
    {
        $user = Auth::user();

        if (!$user || $user->role !== 'ADMIN') {
            return $this->respond(null, 'Admin access required', Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    public function indexPublic()
    {
        $slides = $this->heroSlideService->getPublicSlides();

        return $this->respond(HeroSlideResource::collection($slides));
    }

    public function indexAdmin()
    {
        if ($resp = $this->ensureAdmin()) {
            return $resp;
        }

        $slides = $this->heroSlideService->getAllSlides();

        return $this->respond(HeroSlideResource::collection($slides));
    }

    public function show(string $heroSlideId)
    {
        $slide = $this->heroSlideService->getSlide($heroSlideId);

        return $this->respond(new HeroSlideResource($slide));
    }

    public function store(Request $request)
    {
        if ($resp = $this->ensureAdmin()) {
            return $resp;
        }

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'altText' => 'nullable|string|max:255',
            'imageUrl' => 'required|string|max:2048',
            'imageCloudinaryId' => 'nullable|string|max:255',
            'sortOrder' => 'sometimes|integer|min:0',
            'isActive' => 'sometimes|boolean',
        ]);

        $slide = $this->heroSlideService->createSlide($data);

        return $this->respond(new HeroSlideResource($slide), 'Hero slide created', Response::HTTP_CREATED);
    }

    public function update(Request $request, string $heroSlideId)
    {
        if ($resp = $this->ensureAdmin()) {
            return $resp;
        }

        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'altText' => 'sometimes|nullable|string|max:255',
            'imageUrl' => 'sometimes|required|string|max:2048',
            'imageCloudinaryId' => 'sometimes|nullable|string|max:255',
            'sortOrder' => 'sometimes|integer|min:0',
            'isActive' => 'sometimes|boolean',
        ]);

        $slide = $this->heroSlideService->updateSlide($heroSlideId, $data);

        return $this->respond(new HeroSlideResource($slide), 'Hero slide updated');
    }

    public function destroy(string $heroSlideId)
    {
        if ($resp = $this->ensureAdmin()) {
            return $resp;
        }

        $this->heroSlideService->deleteSlide($heroSlideId);

        return $this->respond(null, 'Hero slide deleted');
    }
}
