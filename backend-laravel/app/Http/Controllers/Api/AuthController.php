<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * Login user and create JWT token
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        try {
            Log::info('Intento de login', ['username' => $request->username]);

            // Find user by username
            $user = User::where('username', $request->username)
                ->with('rol')
                ->first();

            if (! $user) {
                Log::warning('Usuario no encontrado', ['username' => $request->username]);

                return response()->json([
                    'message' => 'Credenciales inválidas',
                ], 401);
            }

            if (! Hash::check($request->password, $user->password)) {
                Log::warning('Contraseña incorrecta', ['username' => $request->username]);

                return response()->json([
                    'message' => 'Credenciales inválidas',
                ], 401);
            }

            // Verify JWT configuration
            if (! config('jwt.secret')) {
                Log::error('JWT_SECRET no configurado');

                return response()->json([
                    'message' => 'Error de configuración del servidor',
                ], 500);
            }

            // Generate JWT token
            $token = auth('api')->login($user);

            if (! $token) {
                Log::error('No se pudo generar el token JWT', ['user_id' => $user->id]);

                return response()->json([
                    'message' => 'Error al generar el token de autenticación',
                ], 500);
            }

            Log::info('Login exitoso', ['user_id' => $user->id, 'username' => $user->username]);

            return response()->json([
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'bearer',
            ]);

        } catch (\Exception $e) {
            Log::error('Error en login: '.$e->getMessage(), [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error en el proceso de autenticación',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor',
            ], 500);
        }
    }

    /**
     * Get authenticated user
     */
    public function me()
    {
        return response()->json(auth('api')->user()->load('rol'));
    }

    /**
     * Logout user (invalidate token)
     */
    public function logout()
    {
        auth('api')->logout();

        return response()->json([
            'message' => 'Sesión cerrada exitosamente',
        ]);
    }

    /**
     * Refresh JWT token
     */
    public function refresh()
    {
        return response()->json([
            'access_token' => auth('api')->refresh(),
            'token_type' => 'bearer',
        ]);
    }
}
