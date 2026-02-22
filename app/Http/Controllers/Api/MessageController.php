<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incidence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function store(Request $request, int $incidenceId): JsonResponse
    {
        $user = $request->user();

        $incidence = Incidence::where('incidence_id', $incidenceId)
            ->where('user_id', $user->user_id)
            ->first();

        if (!$incidence) {
            return response()->json([
                'message' => 'Incidence not found.',
            ], 404);
        }

        if (($incidence->status ?? null) !== (Incidence::STATUS_ACTIVE ?? 'active')) {
            return response()->json([
                'message' => 'Cannot send messages to an inactive incidence.',
            ], 422);
        }

        $data = $request->validate([
            'message' => ['required', 'string', 'max:255'],
        ]);

        $message = $incidence->messages()->create([
            'user_id' => $user->user_id,
            'message' => $data['message'],
            'sent_date' => now(),
        ]);

        $message->load('user');

        return response()->json([
            'message' => 'Message sent successfully.',
            'data' => [
                'message_id' => $message->message_id,
                'incidence_id' => $message->incidence_id,
                'user_id' => $message->user_id,
                'message' => $message->message,
                'sent_date' => $message->sent_date,
                'user' => $message->user ? [
                    'user_id' => $message->user->user_id,
                    'name' => $message->user->name,
                ] : null,
            ],
        ], 201);
    }
}
