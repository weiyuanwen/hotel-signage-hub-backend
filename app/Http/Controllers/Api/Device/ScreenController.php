<?php

namespace App\Http\Controllers\Api\Device;

use App\Domains\Device\ScreenDataBuilder;
use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ScreenController extends Controller
{
    public function __construct(private ScreenDataBuilder $builder) {}

    public function show(Request $request): JsonResponse|Response
    {
        /** @var Device $device */
        $device = $request->user();
        $payload = $this->builder->forDevice($device);
        $revision = (string) $payload['room']['content_revision'];

        if ($request->headers->get('If-None-Match') === $revision) {
            return response()->noContent(304)->withHeaders(['ETag' => $revision]);
        }

        return response()->json($payload)->withHeaders(['ETag' => $revision]);
    }
}
