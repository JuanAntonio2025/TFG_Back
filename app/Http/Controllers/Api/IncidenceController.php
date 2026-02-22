<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incidence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncidenceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $incidences = Incidence::where('user_id', $user->user_id)
            ->orderByDesc('incidence_id')
            ->get()
            ->map(function ($incidence) {
                return $this->formatIncidence($incidence);
            });

        return response()->json([
            'data' => $incidences,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'type_of_incident' => ['required', 'string', 'max:255'],
            'initial_message' => ['nullable', 'string', 'max:255'],
        ]);

        $incidence = Incidence::create([
            'user_id' => $user->user_id,
            'subject' => $data['subject'],
            'type_of_incident' => $data['type_of_incident'],
            'creation_date' => now(),
            'status' => Incidence::STATUS_ACTIVE ?? 'active',
        ]);

        // Si se envía mensaje inicial, lo creamos
        if (!empty($data['initial_message'])) {
            $incidence->messages()->create([
                'user_id' => $user->user_id,
                'message' => $data['initial_message'],
                'sent_date' => now(),
            ]);
        }

        $incidence->load(['messages.user']);

        return response()->json([
            'message' => 'Incidence created successfully.',
            'data' => $this->formatIncidenceWithMessages($incidence),
        ], 201);
    }

    public function show(Request $request, int $incidenceId): JsonResponse
    {
        $user = $request->user();

        $incidence = Incidence::with(['messages.user'])
            ->where('incidence_id', $incidenceId)
            ->where('user_id', $user->user_id)
            ->first();

        if (!$incidence) {
            return response()->json([
                'message' => 'Incidence not found.',
            ], 404);
        }

        return response()->json([
            'data' => $this->formatIncidenceWithMessages($incidence),
        ]);
    }

    private function formatIncidence(Incidence $incidence): array
    {
        return [
            'incidence_id' => $incidence->incidence_id,
            'user_id' => $incidence->user_id,
            'subject' => $incidence->subject,
            'type_of_incident' => $incidence->type_of_incident,
            'creation_date' => $incidence->creation_date,
            'status' => $incidence->status,
        ];
    }

    private function formatIncidenceWithMessages(Incidence $incidence): array
    {
        return [
            ...$this->formatIncidence($incidence),
            'messages' => $incidence->messages
                ->sortBy('message_id')
                ->values()
                ->map(function ($message) {
                    return [
                        'message_id' => $message->message_id,
                        'incidence_id' => $message->incidence_id,
                        'user_id' => $message->user_id,
                        'message' => $message->message,
                        'sent_date' => $message->sent_date,
                        'user' => $message->user ? [
                            'user_id' => $message->user->user_id,
                            'name' => $message->user->name,
                        ] : null,
                    ];
                }),
        ];
    }
}
