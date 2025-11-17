<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WithdrawResource;
use App\Services\WithdrawService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WithdrawController extends Controller
{
    public function __construct(
        private readonly WithdrawService $withdrawService
    ) {
    }

    /**
     * Create a new withdraw request.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'bank' => 'required|array',
            'bank.bank' => 'required|string',
            'bank.agency' => 'nullable|string',
            'bank.account' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $withdraw = $this->withdrawService->createWithdraw($request->user(), $validator->validated());

            return response()->json([
                'success' => true,
                'data' => new WithdrawResource($withdraw),
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating withdraw',
            ], 500);
        }
    }
}

