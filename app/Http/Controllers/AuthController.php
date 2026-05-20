<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Modules\Auth\Actions\LoginUser;
use App\Modules\Auth\Actions\RegisterTenantOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterTenantOwner $action): JsonResponse
    {
        $user = $action(
            name: $request->input('name'),
            email: $request->input('email'),
            password: $request->input('password'),
            tenantName: $request->input('tenant_name'),
            tenantSlug: $request->input('tenant_slug'),
        );

        return response()->json(
            new UserResource($user),
            Response::HTTP_CREATED
        );
    }

    public function login(LoginRequest $request, LoginUser $action): JsonResponse
    {
        $user = $action(
            email: $request->input('email'),
            password: $request->input('password'),
        );

        if (! $user) {
            return response()->json(
                ['message' => 'Credenciais inválidas.'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    public function logout(): JsonResponse
    {
        auth()->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout realizado com sucesso.']);
    }
}
