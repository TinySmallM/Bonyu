<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Master;
use App\Models\Referral;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function attach(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        // Берём мастера из middleware (а не из заголовка напрямую)
        $currentMaster = $request->attributes->get('current_master');

        if (!$currentMaster) {
            return response()->json([
                'error' => 'X-Master-Id header is required or invalid'
            ], 400);
        }

        // Находим мастера по реферальному коду
        $referrer = Master::where('referral_code', $request->code)->first();

        if (!$referrer) {
            return response()->json([
                'error' => 'Invalid referral code'
            ], 404);
        }

        // Проверка: нельзя закрепиться за собой
        if ($currentMaster->id === $referrer->id) {
            return response()->json([
                'error' => 'Cannot attach to yourself'
            ], 422);
        }

        // Проверка: нет ли уже привязки у текущего мастера
        $existingReferral = Referral::where('referred_master_id', $currentMaster->id)->first();

        if ($existingReferral) {
            return response()->json([
                'error' => 'Already attached to a referrer'
            ], 422);
        }

        // Создаём привязку
        $referral = Referral::create([
            'referrer_master_id' => $referrer->id,
            'referred_master_id' => $currentMaster->id,
            'status' => Referral::STATUS_PENDING,
        ]);

        return response()->json([
            'message' => 'Successfully attached',
            'data' => [
                'referral_id' => $referral->id,
                'referrer' => [
                    'id' => $referrer->id,
                    'name' => $referrer->name,
                    'referral_code' => $referrer->referral_code,
                ],
                'status' => $referral->status,
                'attached_at' => $referral->created_at->toDateTimeString(),
            ]
        ], 201);
    }
}