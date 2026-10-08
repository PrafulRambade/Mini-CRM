<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * Issue a personal access token (Bearer) for valid credentials.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = $request->authenticateUser();

        $expiration = config('sanctum.expiration');
        $expiresAt = $expiration ? now()->addMinutes((int) $expiration) : null;

        $token = $user->createToken(
            $request->string('device_name', 'api')->limit(100)->toString(),
            ['*'],
            $expiresAt,
        );

        app(ActivityLogger::class)->log('auth.login', 'Signed in (API token issued)', $user, ['channel' => 'api'], $user);

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'expires_at' => $expiresAt?->toIso8601String(),
            'user' => new UserResource($user),
        ], 201);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * Revoke the token used for this request.
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        app(ActivityLogger::class)->log('auth.logout', 'Signed out (API token revoked)', $request->user(), ['channel' => 'api']);

        return response()->json(['message' => 'Logged out successfully.']);
    }
}
