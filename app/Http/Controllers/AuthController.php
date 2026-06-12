<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Modules\Auth\Actions\LoginUser;
use App\Modules\Auth\Actions\RegisterTenantOwner;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * Web (Inertia) — session-based login.
     */
    public function webLogin(LoginRequest $request): RedirectResponse
    {
        $user = User::where('email', $request->input('email'))->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Email ou senha incorretos.',
            ]);
        }

        // Conta agendada pra exclusão: logar dentro da janela de 30 dias recupera tudo.
        $recovered = false;
        $tenant = $user->tenant()->withTrashed()->first();
        if ($tenant && $tenant->trashed()) {
            $this->recoverAccount($tenant);
            $recovered = true;
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'Esta conta está desativada.',
            ]);
        }

        Auth::login($user, remember: $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended('/')->with('account_recovered', $recovered);
    }

    /**
     * Reverte a exclusão de uma barbearia ainda dentro da janela de recuperação.
     * A assinatura foi cancelada na exclusão, então volta pro trial (se ainda válido)
     * ou fica como cancelada — o dono escolhe um plano de novo.
     */
    private function recoverAccount(Tenant $tenant): void
    {
        $tenant->restore();
        $tenant->purge_scheduled_at = null;
        $tenant->status = ($tenant->trial_ends_at && $tenant->trial_ends_at->isFuture()) ? 'trial' : 'cancelled';
        $tenant->save();
    }

    /**
     * Web (Inertia) — register tenant owner and start session.
     */
    public function webRegister(RegisterRequest $request, RegisterTenantOwner $action): RedirectResponse
    {
        $user = $action(
            name: $request->input('name'),
            email: $request->input('email'),
            password: $request->input('password'),
            phone: $request->input('phone'),
        );

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/onboarding');
    }

    /**
     * Web (Inertia) — session logout.
     */
    public function webLogout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    /**
     * API — token-based register (mobile / future).
     */
    public function register(RegisterRequest $request, RegisterTenantOwner $action): JsonResponse
    {
        $user = $action(
            name: $request->input('name'),
            email: $request->input('email'),
            password: $request->input('password'),
            phone: $request->input('phone'),
        );

        return response()->json(
            new UserResource($user),
            Response::HTTP_CREATED
        );
    }

    /**
     * API — token-based login (mobile / future).
     */
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

    /**
     * API — token logout.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $user->tokens()->whereKey($token->getKey())->delete();
        }

        return response()->json(['message' => 'Logout realizado com sucesso.']);
    }

    public function sendResetLink(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        Password::sendResetLink($request->only('email'));

        // Sempre 200 — não revela se o email existe.
        return response()->json(['message' => 'Se este email existir, você receberá o link em alguns minutos.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|max:150',
            'token' => 'required|string|max:255',
            'password' => 'required|string|min:8|max:72|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Senha redefinida com sucesso. Faça login novamente.']);
        }

        return response()->json(['message' => 'Não foi possível redefinir a senha. O link expirou ou é inválido.'], 422);
    }
}
