<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesImageUploads;
use App\Modules\Billing\Services\BillingService;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Rules\BrazilianPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    use ManagesImageUploads;

    public function getTenantSettings(): JsonResponse
    {
        $tenant = app('tenant');
        $settings = $this->parseSettings($tenant);

        return response()->json([
            'data' => [
                'name' => $tenant->name,
                'logo_url' => $tenant->logoUrl(),
                'timezone' => data_get($settings, 'timezone', 'America/Manaus'),
                'cancellation_policy_hours' => data_get($settings, 'cancellation_policy_hours', 24),
                'default_commission_percentage' => data_get($settings, 'financial.default_commission_percentage', 50),
                'business_hours' => data_get($settings, 'business_hours', $this->defaultBusinessHours()),
            ],
        ]);
    }

    public function updateTenantSettings(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|min:2|max:100',
            'timezone' => 'sometimes|string|max:60',
            'cancellation_policy_hours' => 'sometimes|integer|min:0|max:168',
            'default_commission_percentage' => 'sometimes|numeric|min:0|max:100',
        ]);

        $tenant = app('tenant');
        $settings = $this->parseSettings($tenant);

        if ($request->has('name')) {
            $tenant->name = $request->input('name');
        }
        if ($request->has('timezone')) {
            $settings['timezone'] = $request->input('timezone');
        }
        if ($request->has('cancellation_policy_hours')) {
            $settings['cancellation_policy_hours'] = (int) $request->input('cancellation_policy_hours');
        }
        if ($request->has('default_commission_percentage')) {
            $settings['financial']['default_commission_percentage'] = (float) $request->input('default_commission_percentage');
        }

        $tenant->settings = $settings;
        $tenant->save();

        return $this->getTenantSettings();
    }

    public function updateBusinessHours(Request $request): JsonResponse
    {
        $request->validate([
            'business_hours' => 'required|array',
            'business_hours.*.day_of_week' => 'required|integer|min:0|max:6',
            'business_hours.*.start_time' => 'nullable|string',
            'business_hours.*.end_time' => 'nullable|string',
            'business_hours.*.closed' => 'sometimes|boolean',
        ]);

        $tenant = app('tenant');
        $settings = $this->parseSettings($tenant);
        $settings['business_hours'] = $request->input('business_hours');

        $tenant->settings = $settings;
        $tenant->save();

        return response()->json(['data' => ['business_hours' => $settings['business_hours']]]);
    }

    public function listTeam(): JsonResponse
    {
        $users = User::where('tenant_id', app('tenant')->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role instanceof \BackedEnum ? $u->role->value : $u->role,
            ]);

        return response()->json(['data' => $users]);
    }

    public function inviteTeamMember(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|email|max:150|unique:users,email',
            'role' => 'required|in:owner,manager,receptionist,barber',
            'password' => 'required|string|min:8|max:72',
        ]);

        $tenant = app('tenant');

        if (! $tenant->canAddTeamMember()) {
            return response()->json([
                'message' => "Seu plano permite até {$tenant->effectiveStaffLimit()} funcionários (incluindo você). Faça upgrade para adicionar mais.",
            ], Response::HTTP_FORBIDDEN);
        }

        $user = User::create([
            'tenant_id' => app('tenant')->id,
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => $request->input('password'),
            'role' => $request->input('role'),
        ]);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role instanceof \BackedEnum ? $user->role->value : $user->role,
            ],
        ], Response::HTTP_CREATED);
    }

    public function removeTeamMember(User $user): Response
    {
        if ($user->tenant_id !== app('tenant')->id) {
            abort(403);
        }

        $ownerCount = User::where('tenant_id', app('tenant')->id)
            ->where('role', 'owner')
            ->count();

        if ((($user->role instanceof \BackedEnum ? $user->role->value : $user->role) === 'owner') && $ownerCount <= 1) {
            abort(422, 'Não é possível remover o último dono da barbearia.');
        }

        $user->delete();

        return response()->noContent();
    }

    public function getProfile(): JsonResponse
    {
        $user = auth()->user();
        $barber = $user->barber; // dono/barbeiro-com-login têm um Barber vinculado (mesma pessoa)

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                // Dono/barbeiro são a mesma pessoa: a foto é uma só. Se a conta não tem
                // avatar próprio, cai na foto do registro da equipe (e vice-versa no upload).
                'avatar_url' => $user->avatarUrl() ?? $barber?->photoUrl(),
                'role' => $user->role instanceof \BackedEnum ? $user->role->value : $user->role,
                // Telefone vive no Barber. Só faz sentido editar aqui quem é barbeiro também.
                'phone' => $barber?->phone,
                'has_barber' => (bool) $barber,
            ],
        ]);
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate($this->imageRules('avatar'));

        $user = auth()->user();
        $user->avatar_path = $this->storeImage($request->file('avatar'), 'avatars/users', $user->avatar_path);
        $user->save();

        // Mesma pessoa, mesma foto: espelha no registro da equipe para "Meu perfil" e
        // "Equipe" nunca divergirem (apontam para o mesmo arquivo).
        $this->syncPhotoToBarber($user, $user->avatar_path);

        return $this->getProfile();
    }

    public function deleteAvatar(): JsonResponse
    {
        $user = auth()->user();
        $this->deleteImage($user->avatar_path);
        $user->avatar_path = null;
        $user->save();

        $this->syncPhotoToBarber($user, null);

        return $this->getProfile();
    }

    /**
     * Mantém a foto do barbeiro vinculado igual à da conta (dono/barbeiro com login).
     * Não chama deleteImage de novo: o arquivo já foi tratado no lado da conta — aqui
     * só copiamos a referência (o mesmo path) para não apagar duas vezes.
     */
    private function syncPhotoToBarber(User $user, ?string $path): void
    {
        $barber = $user->barber;
        if ($barber && $barber->photo_path !== $path) {
            $barber->photo_path = $path;
            $barber->save();
        }
    }

    public function updateLogo(Request $request): JsonResponse
    {
        $request->validate($this->imageRules('logo'));

        $tenant = app('tenant');
        $tenant->logo_path = $this->storeImage($request->file('logo'), 'logos', $tenant->logo_path);
        $tenant->save();

        return $this->getTenantSettings();
    }

    public function deleteLogo(): JsonResponse
    {
        $tenant = app('tenant');
        $this->deleteImage($tenant->logo_path);
        $tenant->logo_path = null;
        $tenant->save();

        return $this->getTenantSettings();
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = auth()->user();
        $barber = $user->barber;

        // Telefone chega mascarado da UI; normaliza pra dígitos antes de validar/salvar.
        if ($request->has('phone')) {
            $digits = preg_replace('/\D/', '', (string) $request->input('phone'));
            $request->merge(['phone' => $digits !== '' ? $digits : null]);
        }

        // Só dados pessoais. Troca de senha é um endpoint próprio (updatePassword) pra
        // não acontecer de salvar nome/email e a senha ser silenciosamente ignorada.
        $request->validate([
            'name' => 'sometimes|string|min:2|max:100',
            'email' => 'sometimes|email|max:150|unique:users,email,'.$user->id,
            'phone' => ['sometimes', 'nullable', 'string', new BrazilianPhone],
        ]);

        if ($request->has('name')) {
            $user->name = $request->input('name');
        }
        if ($request->has('email')) {
            $user->email = $request->input('email');
        }
        $user->save();

        // Dono/barbeiro são a MESMA pessoa: propaga nome e telefone pro registro da equipe,
        // pra "Meu perfil" e "Equipe" nunca divergirem (eram tratados como 2 perfis).
        if ($barber) {
            if ($request->has('name')) {
                $barber->name = $request->input('name');
            }
            if ($request->filled('phone')) {
                $barber->phone = $request->input('phone');
            }
            if ($barber->isDirty()) {
                $barber->save();
            }
        }

        return $this->getProfile();
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Senha atual obrigatória; nova obrigatória, min 8 e confirmada. Nada de no-op:
        // se faltar qualquer campo, é 422 com erro no campo certo.
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|max:72|confirmed',
        ]);

        if (! Hash::check($request->input('current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'A senha atual está incorreta.',
            ]);
        }

        // A nova senha tem que ser diferente da atual (padrão de sistemas modernos).
        if (Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'A nova senha precisa ser diferente da atual.',
            ]);
        }

        $user->password = $request->input('password');
        $user->save();

        return response()->json(['message' => 'Senha alterada com sucesso.']);
    }

    /**
     * Exclusão de conta (só o dono). Encerra a barbearia inteira: pede a senha de novo
     * (re-autenticação), cancela a assinatura e some do sistema na hora.
     *
     * É REVERSÍVEL por 30 dias: faz só soft-delete do tenant. Os dados — e os emails da
     * equipe, que ficam RESERVADOS (as linhas de usuário continuam intactas) — seguem no
     * banco. Nesse prazo dá pra recuperar tudo só fazendo login de novo
     * (AuthController::webLogin restaura). Passados os 30 dias, o comando accounts:purge
     * apaga de vez (cascade) e só então o email é liberado pra um novo cadastro.
     */
    public function deleteAccount(Request $request, BillingService $billing): JsonResponse
    {
        $user = auth()->user();
        $tenant = $user->tenant;

        if (! $user->isOwner()) {
            abort(403, 'Apenas o dono pode excluir a conta da barbearia.');
        }

        $request->validate(['current_password' => 'required|string']);

        if (! Hash::check($request->input('current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'A senha está incorreta.',
            ]);
        }

        // Cancela a assinatura pra não gerar cobrança depois. Best-effort: se o Asaas
        // falhar, a exclusão segue assim mesmo (não dá pra prender o usuário por isso).
        if ($tenant->asaas_subscription_id) {
            try {
                $billing->cancelSubscription($tenant);
            } catch (\Throwable $e) {
                Log::warning('Falha ao cancelar assinatura ao excluir conta', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        DB::transaction(function () use ($tenant) {
            // Soft delete: some das consultas normais e o acesso fecha, mas tudo (inclusive
            // os emails) fica guardado e recuperável até a purga.
            $tenant->status = 'cancelled';
            $tenant->purge_scheduled_at = now()->addDays(30);
            $tenant->save();
            $tenant->delete();
        });

        // Derruba a sessão: o acesso fecha imediatamente. (Logar de novo recupera a conta.)
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Sua conta foi excluída. Você tem 30 dias para reativá-la: basta fazer login novamente. Depois desse período, ela é apagada em definitivo.',
            'redirect' => '/login',
        ]);
    }

    private function parseSettings(Tenant $tenant): array
    {
        $raw = $tenant->settings ?? [];
        if (is_string($raw)) {
            $raw = json_decode($raw, true) ?: [];
        }

        return $raw;
    }

    private function defaultBusinessHours(): array
    {
        return [
            ['day_of_week' => 0, 'start_time' => null, 'end_time' => null, 'closed' => true],
            ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '18:00', 'closed' => false],
            ['day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '18:00', 'closed' => false],
            ['day_of_week' => 3, 'start_time' => '09:00', 'end_time' => '18:00', 'closed' => false],
            ['day_of_week' => 4, 'start_time' => '09:00', 'end_time' => '18:00', 'closed' => false],
            ['day_of_week' => 5, 'start_time' => '09:00', 'end_time' => '18:00', 'closed' => false],
            ['day_of_week' => 6, 'start_time' => '09:00', 'end_time' => '14:00', 'closed' => false],
        ];
    }
}
