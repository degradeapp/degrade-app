<?php

namespace App\Http\Controllers;

use App\Modules\Notification\Models\NotificationSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        $settings = $this->getOrCreate();

        return response()->json([
            'data' => [
                'channels' => $settings->channels ?? ['email', 'whatsapp'],
                'reminder_24h_before' => (bool) $settings->reminder_24h_before,
                'reminder_1h_before' => (bool) $settings->reminder_1h_before,
                'appointment_confirmed' => (bool) $settings->appointment_confirmed,
                'appointment_rescheduled' => (bool) $settings->appointment_rescheduled,
                'appointment_cancelled' => (bool) $settings->appointment_cancelled,
                'email_from' => $settings->email_from,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'channels' => 'sometimes|array',
            'channels.*' => 'in:email,whatsapp,sms',
            'reminder_24h_before' => 'sometimes|boolean',
            'reminder_1h_before' => 'sometimes|boolean',
            'appointment_confirmed' => 'sometimes|boolean',
            'appointment_rescheduled' => 'sometimes|boolean',
            'appointment_cancelled' => 'sometimes|boolean',
            'email_from' => 'sometimes|nullable|email',
        ]);

        $settings = $this->getOrCreate();
        $settings->fill($request->only([
            'channels',
            'reminder_24h_before',
            'reminder_1h_before',
            'appointment_confirmed',
            'appointment_rescheduled',
            'appointment_cancelled',
            'email_from',
        ]));
        $settings->save();

        return $this->show();
    }

    private function getOrCreate(): NotificationSetting
    {
        return NotificationSetting::firstOrCreate(
            ['tenant_id' => app('tenant')->id],
            [
                'channels' => ['email', 'whatsapp'],
                'reminder_24h_before' => true,
                'reminder_1h_before' => true,
                'appointment_confirmed' => true,
                'appointment_rescheduled' => true,
                'appointment_cancelled' => true,
            ]
        );
    }
}
