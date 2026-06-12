<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesImageUploads;
use App\Http\Requests\CreateTimeOffRequest;
use App\Http\Requests\StoreBarberRequest;
use App\Http\Requests\UpdateBarberRequest;
use App\Http\Requests\UpsertScheduleRequest;
use App\Http\Resources\BarberResource;
use App\Http\Resources\BarberTimeOffResource;
use App\Modules\Barber\Actions\CreateBarber;
use App\Modules\Barber\Actions\CreateBarberTimeOff;
use App\Modules\Barber\Actions\DeleteBarber;
use App\Modules\Barber\Actions\DeleteBarberTimeOff;
use App\Modules\Barber\Actions\UpdateBarber;
use App\Modules\Barber\Actions\UpsertBarberSchedule;
use App\Modules\Barber\Models\Barber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BarberController extends Controller
{
    use ManagesImageUploads;

    public function index(): AnonymousResourceCollection
    {
        $query = Barber::with('schedules', 'user:id,avatar_path', 'unit:id,name');

        if ($q = request('q')) {
            $query->where('name', 'like', "%{$q}%");
        }

        if (request()->has('is_active')) {
            $query->where('is_active', request()->boolean('is_active'));
        }

        $barbers = $query->orderBy('name')->paginate(request('per_page', 50));

        return BarberResource::collection($barbers);
    }

    public function store(StoreBarberRequest $request, CreateBarber $action): JsonResponse
    {
        $tenant = app('tenant');

        if (! $tenant->canAddBarber()) {
            return response()->json([
                'message' => "Seu plano permite até {$tenant->effectiveStaffLimit()} funcionários (incluindo você). Faça upgrade para adicionar mais.",
            ], Response::HTTP_FORBIDDEN);
        }

        $barber = $action(
            name: $request->input('name'),
            phone: $request->input('phone'),
            userId: $request->input('user_id'),
            defaultCommissionPercentage: $request->input('default_commission_percentage'),
            unitId: $request->input('unit_id') ? (int) $request->input('unit_id') : null,
        );

        $this->applyDefaultSchedule($barber, $tenant);

        return response()->json(
            new BarberResource($barber->load('schedules', 'timeOffs')),
            Response::HTTP_CREATED
        );
    }

    /**
     * Dá ao barbeiro recém-criado uma agenda inicial, para ele já ficar
     * "agendável". Usa o horário de funcionamento da barbearia; se ainda não
     * houver, cai no padrão Seg–Sáb 09:00–19:00. Editável depois em /schedule.
     */
    private function applyDefaultSchedule(Barber $barber, $tenant): void
    {
        $rows = collect($tenant->setting('business_hours', []))
            ->filter(fn ($h) => ! ($h['closed'] ?? false) && ! empty($h['start_time']) && ! empty($h['end_time']))
            ->map(fn ($h) => [
                'tenant_id' => $tenant->id,
                'day_of_week' => (int) $h['day_of_week'],
                'start_time' => $h['start_time'],
                'end_time' => $h['end_time'],
            ])
            ->values()
            ->all();

        if (empty($rows)) {
            foreach (range(1, 6) as $dow) { // Seg(1)–Sáb(6), domingo(0) fechado
                $rows[] = [
                    'tenant_id' => $tenant->id,
                    'day_of_week' => $dow,
                    'start_time' => '09:00',
                    'end_time' => '19:00',
                ];
            }
        }

        foreach ($rows as $row) {
            $barber->schedules()->create($row);
        }
    }

    public function show(Barber $barber): JsonResponse
    {
        $this->authorize('view', $barber);

        return response()->json(new BarberResource($barber->load('schedules', 'timeOffs', 'user:id,avatar_path')));
    }

    public function update(Barber $barber, UpdateBarberRequest $request, UpdateBarber $action): JsonResponse
    {
        $this->authorize('update', $barber);

        $updated = $action(
            barber: $barber,
            name: $request->input('name'),
            phone: $request->input('phone'),
            defaultCommissionPercentage: $request->input('default_commission_percentage'),
            isActive: $request->input('is_active'),
            unitId: $request->input('unit_id') ? (int) $request->input('unit_id') : null,
        );

        return response()->json(new BarberResource($updated->load('schedules', 'timeOffs')));
    }

    public function destroy(Barber $barber, DeleteBarber $action): Response|JsonResponse
    {
        $this->authorize('delete', $barber);

        // O dono é barbeiro por padrão e não pode ser removido da equipe — isso
        // criaria um beco sem saída (sem perfil, ele não consegue se agendar).
        // Para sair de vez, o caminho é "Excluir conta". Para sumir da agenda, desativar.
        if (optional($barber->user)->isOwner()) {
            return response()->json([
                'message' => 'Você é o dono e não pode se remover da equipe. Para não aparecer na agenda, desative o seu perfil aqui na edição.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Excluir é soft-delete: a linha do barbeiro fica guardada, então comissões e
        // relatórios antigos CONTINUAM mostrando o nome dele (lidos com withTrashed).
        // Por isso não bloqueamos quem tem histórico — o dono decide. (Desativar segue
        // sendo a opção reversível; excluir é definitivo na visão da equipe/agenda.)
        $action($barber, auth()->id());

        return response()->noContent();
    }

    public function updatePhoto(Barber $barber, \Illuminate\Http\Request $request): JsonResponse
    {
        // Foto do barbeiro é conteúdo da barbearia (a cara que o cliente vê na equipe),
        // então segue a mesma permissão de gerir o barbeiro — não a conta pessoal dele.
        $this->authorize('update', $barber);
        $request->validate($this->imageRules('photo'));

        $barber->photo_path = $this->storeImage($request->file('photo'), 'barbers/photos', $barber->photo_path);
        $barber->save();

        // Se esse barbeiro tem login (é o dono ou um barbeiro com conta), a foto da equipe
        // É a foto de perfil dele: espelha no usuário para não virarem duas fotos diferentes.
        $this->syncPhotoToUser($barber, $barber->photo_path);

        return response()->json(new BarberResource($barber->load('schedules', 'timeOffs')));
    }

    public function deletePhoto(Barber $barber): JsonResponse
    {
        $this->authorize('update', $barber);

        $this->deleteImage($barber->photo_path);
        $barber->photo_path = null;
        $barber->save();

        $this->syncPhotoToUser($barber, null);

        return response()->json(new BarberResource($barber->load('schedules', 'timeOffs')));
    }

    /**
     * Mantém o avatar da conta vinculada igual ao do barbeiro. Copia só a referência
     * (mesmo arquivo); o arquivo em si já foi tratado no lado do barbeiro.
     */
    private function syncPhotoToUser(Barber $barber, ?string $path): void
    {
        $user = $barber->user;
        if ($user && $user->avatar_path !== $path) {
            $user->avatar_path = $path;
            $user->save();
        }
    }

    public function schedule(
        Barber $barber,
        int $day,
        UpsertScheduleRequest $request,
        UpsertBarberSchedule $action
    ): JsonResponse {
        $this->authorize('update', $barber);

        $schedule = $action(
            barber: $barber,
            dayOfWeek: $day,
            startTime: $request->input('start_time'),
            endTime: $request->input('end_time'),
        );

        return response()->json([
            'message' => 'Horário atualizado com sucesso.',
            'schedule' => $schedule,
        ]);
    }

    public function timeOff(Barber $barber, CreateTimeOffRequest $request, CreateBarberTimeOff $action): JsonResponse
    {
        $this->authorize('update', $barber);

        $timeOff = $action(
            barber: $barber,
            date: $request->input('date'),
            endDate: $request->input('end_date'),
            reason: $request->input('reason'),
        );

        return response()->json(
            new BarberTimeOffResource($timeOff),
            Response::HTTP_CREATED
        );
    }

    public function deleteTimeOff(Barber $barber, string $date, DeleteBarberTimeOff $action): Response
    {
        $this->authorize('update', $barber);

        $action($barber, $date);

        return response()->noContent();
    }
}
