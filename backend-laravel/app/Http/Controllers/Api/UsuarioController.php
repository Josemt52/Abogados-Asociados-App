<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index()
    {
        $usuarios = User::with('rol')->get();

        return response()->json($usuarios);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'password' => 'required|string|min:6',
            'rol_id' => 'required|exists:roles,id',
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $usuario = User::create($validated);
        $usuario->load('rol');

        return response()->json([
            'message' => 'Usuario creado exitosamente',
            'usuario' => $usuario,
        ], 201);
    }

    /**
     * Display the specified user.
     */
    public function show(string $id)
    {
        $usuario = User::with('rol')->findOrFail($id);

        return response()->json($usuario);
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, string $id)
    {
        $usuario = User::findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'username' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('users')->ignore($usuario->id),
            ],
            'password' => 'sometimes|nullable|string|min:6',
            'rol_id' => 'sometimes|required|exists:roles,id',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $usuario->update($validated);
        $usuario->load('rol');

        return response()->json([
            'message' => 'Usuario actualizado exitosamente',
            'usuario' => $usuario,
        ]);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(string $id)
    {
        $usuario = User::findOrFail($id);

        // Evitar que el usuario se elimine a sí mismo
        /** @var \App\Models\User|null $currentUser */
        $currentUser = auth('api')->user();
        if ($currentUser && $usuario->id == $currentUser->id) {
            return response()->json([
                'error' => 'No puedes eliminar tu propio usuario',
            ], 400);
        }

        $usuario->delete();

        return response()->json([
            'message' => 'Usuario eliminado exitosamente',
        ]);
    }
}
