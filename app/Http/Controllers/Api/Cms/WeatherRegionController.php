<?php

namespace App\Http\Controllers\Api\Cms;

use App\Domains\Content\WeatherRegion;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeatherRegionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('rooms.view'), 403);

        return response()->json([
            'data' => array_values(WeatherRegion::all()),
        ]);
    }
}
