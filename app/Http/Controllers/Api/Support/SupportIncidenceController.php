<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Controller;
use App\Models\Incidence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportIncidenceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Incidence::with('user')
            ->orderByDesc('incidence_id');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $incidences = $query->get()->map(function ($incidence) {
            return $this->formatIncidence($incidence);
        });

        return response()->json([
            'data' => $incidences,
        ]);
    }

    public function show(int $incidenceId): JsonResponse
    {
        $incidence = Incidence::with(['user', 'messages.user'])->find($incidenceId);

        if (!$incidence) {
            return response()->json([
                'message' => 'Incidence not found.',
            ], 404);
        }

        return response()->json([
            'data' => [
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
            ],
        ]);
    }

    public function updateStatus(Request $request, int $incidenceId): JsonResponse
    {
        $incidence = Incidence::find($incidenceId);

        if (!$incidence) {
            return response()->json([
                'message' => 'Incidence not found.',
            ], 404);
        }

        $data = $request->validate([
            'status' => ['required', 'in:active,inactive'],
        ]);

        $incidence->status = $data['status'];
        $incidence->save();

        return response()->json([
            'message' => 'Incidence status updated successfully.',
            'data' => [
                'incidence_id' => $incidence->incidence_id,
                'status' => $incidence->status,
            ],
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
            'user' => $incidence->user ? [
                'user_id' => $incidence->user->user_id,
                'name' => $incidence->user->name,
                'email' => $incidence->user->email,
            ] : null,
        ];
    }
}
