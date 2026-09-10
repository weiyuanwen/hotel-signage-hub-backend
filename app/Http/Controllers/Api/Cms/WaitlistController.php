<?php

namespace App\Http\Controllers\Api\Cms;

use App\Domains\Billing\HotelPlan;
use App\Domains\Billing\WaitlistSignupService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WaitlistController extends Controller
{
    public function store(Request $request, WaitlistSignupService $signup): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'hotel_name' => ['nullable', 'string', 'max:255'],
            'plan' => ['nullable', 'string', Rule::in(HotelPlan::keys())],
        ]);

        $result = $signup->register(
            $data['email'],
            $data['hotel_name'] ?? null,
            $data['plan'] ?? HotelPlan::FREE,
        );

        if ($result['status'] === 'existing') {
            return response()->json([
                'status' => 'existing',
                'message' => 'Email này đã có quầy. Đăng nhập để tiếp tục.',
            ], 409);
        }

        return response()->json([
            'status' => 'created',
            'message' => 'Đã gửi tài khoản tới email của bạn.',
        ], 201);
    }
}
