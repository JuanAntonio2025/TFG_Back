<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Role;

class AdminUserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::with('roles')
            ->orderByDesc('user_id')
            ->get();

        return response()->json([
            'data' => $users,
        ]);
    }

    public function show(int $userId): JsonResponse
    {
        $user = User::with(['roles', 'orders', 'incidences'])->find($userId);

        if (!$user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        return response()->json([
            'data' => $user,
        ]);
    }

    public function updateStatus(Request $request, int $userId): JsonResponse
    {
        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        $data = $request->validate([
            'status' => ['required', 'in:active,banned'],
        ]);

        $user->status = $data['status'];
        $user->save();

        return response()->json([
            'message' => 'User status updated successfully.',
            'data' => [
                'user_id' => $user->user_id,
                'status' => $user->status,
            ],
        ]);
    }

    public function roles(): JsonResponse
    {
        $roles = Role::orderBy('name')->get();

        return response()->json([
            'data' => $roles,
        ]);
    }

    public function updateRoles(Request $request, int $userId): JsonResponse
    {
        $user = User::with('roles')->find($userId);

        if (!$user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        $data = $request->validate([
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,role_id'],
        ]);

        $user->roles()->sync($data['role_ids']);
        $user->load('roles');

        return response()->json([
            'message' => 'Roles updated successfully.',
            'data' => $user,
        ]);
    }
}
