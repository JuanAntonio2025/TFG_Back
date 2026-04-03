<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
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

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'status' => ['required', Rule::in(['active', 'banned'])],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,role_id'],
        ]);

        DB::beginTransaction();

        try {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'status' => $data['status'],
                'register_date' => now(),
                'last_access' => null,
            ]);

            if (!empty($data['role_ids'])) {
                $user->roles()->sync($data['role_ids']);
            }

            $user->load('roles');

            DB::commit();

            return response()->json([
                'message' => 'User created successfully.',
                'data' => $user,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'The user could not be created.',
            ], 500);
        }
    }

    public function update(Request $request, int $userId): JsonResponse
    {
        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->user_id, 'user_id'),
            ],
            'password' => ['nullable', 'string', 'min:8'],
            'status' => ['required', Rule::in(['active', 'banned'])],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,role_id'],
        ]);

        DB::beginTransaction();

        try {
            $user->name = $data['name'];
            $user->email = $data['email'];
            $user->status = $data['status'];

            if (!empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }

            $user->save();

            if (array_key_exists('role_ids', $data)) {
                $user->roles()->sync($data['role_ids'] ?? []);
            }

            $user->load('roles');

            DB::commit();

            return response()->json([
                'message' => 'User updated successfully.',
                'data' => $user,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'The user could not be updated.',
            ], 500);
        }
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

    public function destroy(Request $request, int $userId): JsonResponse
    {
        $authUser = $request->user();

        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        if ($authUser->user_id === $user->user_id) {
            return response()->json([
                'message' => 'You cannot delete your own account from the admin panel.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $user->delete();

            DB::commit();

            return response()->json([
                'message' => 'User deleted successfully.',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'The user could not be deleted.',
            ], 500);
        }
    }
}
