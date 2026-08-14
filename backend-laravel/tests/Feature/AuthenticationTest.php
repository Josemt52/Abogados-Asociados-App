<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

#[RequiresPhpExtension('pdo_sqlite')]
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_a_jwt_and_the_authenticated_user(): void
    {
        $role = Role::create(['nombre' => 'ADMIN']);
        User::create([
            'nombre' => 'Administrador',
            'username' => 'admin',
            'password' => 'secret123',
            'rol_id' => $role->id,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'username' => 'admin',
            'password' => 'secret123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.username', 'admin')
            ->assertJsonPath('user.rol.nombre', 'ADMIN')
            ->assertJsonStructure(['access_token', 'token_type']);
    }

    public function test_invalid_login_payload_returns_validation_errors(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username', 'password']);
    }

    public function test_regular_users_cannot_access_admin_routes(): void
    {
        $userRole = Role::create(['nombre' => 'USUARIO']);

        $regularUser = User::create([
            'nombre' => 'Usuario',
            'username' => 'usuario',
            'password' => 'secret123',
            'rol_id' => $userRole->id,
        ]);

        $regularToken = JWTAuth::fromUser($regularUser);

        $this->withToken($regularToken)->getJson('/api/usuarios')->assertForbidden();
    }

    public function test_admin_routes_accept_an_admin_jwt(): void
    {
        $adminRole = Role::create(['nombre' => 'ADMIN']);
        $admin = User::create([
            'nombre' => 'Administrador',
            'username' => 'admin',
            'password' => 'secret123',
            'rol_id' => $adminRole->id,
        ]);

        $adminToken = JWTAuth::fromUser($admin);

        $this->withToken($adminToken)->getJson('/api/usuarios')->assertOk();
    }
}
