<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTokenRequest;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class TokenController extends Controller
{
    public function __invoke(StoreTokenRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if ($user === null || ! Hash::check($request->validated('password'), $user->password)) {
            throw new AuthenticationException('Invalid credentials.');
        }

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
        ], 201);
    }
}
