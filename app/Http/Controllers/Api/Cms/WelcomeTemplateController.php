<?php

namespace App\Http\Controllers\Api\Cms;

use App\Domains\Content\WelcomeTemplateCatalog;
use App\Http\Controllers\Controller;
use App\Models\Hotel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WelcomeTemplateController extends Controller
{
    public function __construct(private WelcomeTemplateCatalog $catalog) {}

    public function index(Request $request, Hotel $hotel): JsonResponse
    {
        abort_unless($request->user()?->can('rooms.view'), 403);

        $includeDisabled = $request->user()?->can('templates.manage') ?? false;

        return response()->json([
            'data' => $this->catalog->toPayload($hotel, $includeDisabled),
        ]);
    }
}
