<?php

namespace App\Http\Controllers\Api\Device;

use App\Domains\Device\PairingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PairingController extends Controller
{
    public function __construct(private PairingService $pairing) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->pairing->requestCode($data['name'] ?? null);

        return response()->json([
            'code' => $result['code']->code,
            'expires_at' => $result['code']->expires_at->toIso8601String(),
        ], 201);
    }

    public function show(string $code): JsonResponse
    {
        $result = $this->pairing->poll(strtoupper($code));

        $status = $result['status'] === 'pending' ? 202 : 200;

        return response()->json($result, $status);
    }

    public function consumeLink(string $token): JsonResponse
    {
        return response()->json($this->pairing->consumeLink($token));
    }
}
