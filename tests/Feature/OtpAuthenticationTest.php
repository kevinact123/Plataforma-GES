<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSessionNotInactive;
use App\Models\AuditoriaAcceso;
use App\Models\Rol;
use App\Models\User;
use App\Notifications\OtpVerificacionNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use ReflectionProperty;
use Tests\TestCase;

class OtpAuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createOtpSchema();
        Notification::fake();
    }

    public function test_correct_login_sends_otp_without_authenticating_user(): void
    {
        $user = $this->createOtpUser();

        $response = $this->login($user);

        $response
            ->assertOk()
            ->assertJsonMissingPath('token')
            ->assertJsonStructure(['correo_enmascarado']);
        Notification::assertSentTo($user, OtpVerificacionNotification::class);
        $notification = Notification::sent($user, OtpVerificacionNotification::class)->first();
        $this->assertContains(
            'Este código es válido durante 45 segundos.',
            $notification->toMail($user)->introLines
        );
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_only_configured_system_administrator_skips_otp(): void
    {
        config(['auth.system_admin_username' => 'admin']);
        $systemAdmin = $this->createOtpUser('Administrador', 'admin');
        $otherAdmin = $this->createOtpUser('Administrador', 'admin.secundario');

        $this->login($systemAdmin)
            ->assertOk()
            ->assertJsonStructure(['token', 'user']);
        $this->assertSame(1, $systemAdmin->tokens()->count());
        Notification::assertNotSentTo($systemAdmin, OtpVerificacionNotification::class);

        $this->login($otherAdmin)
            ->assertOk()
            ->assertJsonMissingPath('token')
            ->assertJsonStructure(['correo_enmascarado']);
        $this->assertSame(0, $otherAdmin->tokens()->count());
        Notification::assertSentTo($otherAdmin, OtpVerificacionNotification::class);
    }

    public function test_correct_otp_within_45_seconds_allows_access(): void
    {
        $user = $this->createOtpUser();
        $this->login($user);
        $code = $this->sentCode($user, 0);

        $this->travel(44)->seconds();

        $this->postJson('/otp/verificar', ['codigo' => $code])
            ->assertOk()
            ->assertJsonStructure(['token', 'user']);
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_incorrect_otp_is_rejected(): void
    {
        $user = $this->createOtpUser();
        $this->login($user);
        $code = $this->sentCode($user, 0);

        $this->postJson('/otp/verificar', ['codigo' => $this->differentCode($code)])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El código ingresado no es válido.');
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_otp_after_45_seconds_is_rejected(): void
    {
        $user = $this->createOtpUser();
        $this->login($user);
        $code = $this->sentCode($user, 0);

        $this->travel(46)->seconds();

        $this->postJson('/otp/verificar', ['codigo' => $code])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El código ha expirado. Solicita uno nuevo.');
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_resending_otp_invalidates_previous_code(): void
    {
        $user = $this->createOtpUser();
        $this->login($user);
        $oldCode = $this->sentCode($user, 0);
        $this->travel(45)->seconds();

        $this->postJson('/otp/reenviar')->assertOk();
        $newCode = $this->sentCode($user, 1);
        $this->assertNotSame($oldCode, $newCode);

        $this->postJson('/otp/verificar', ['codigo' => $oldCode])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El código ingresado no es válido.');
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_new_otp_after_resending_allows_access(): void
    {
        $user = $this->createOtpUser();
        $this->login($user);
        $this->sentCode($user, 0);
        $this->travel(45)->seconds();

        $this->postJson('/otp/reenviar')->assertOk();
        $newCode = $this->sentCode($user, 1);

        $this->postJson('/otp/verificar', ['codigo' => $newCode])
            ->assertOk()
            ->assertJsonStructure(['token', 'user']);
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_login_requests_are_rate_limited(): void
    {
        $user = $this->createOtpUser();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->login($user)->assertOk();
        }

        $this->login($user)->assertStatus(429);
        $this->assertSame(5, Notification::sent($user, OtpVerificacionNotification::class)->count());
    }

    public function test_fifth_incorrect_code_invalidates_otp_and_reaches_attempt_limit(): void
    {
        $user = $this->createOtpUser();
        $this->login($user);
        $code = $this->sentCode($user, 0);
        $wrongCode = $this->differentCode($code);

        for ($attempt = 1; $attempt < 3; $attempt++) {
            $this->postJson('/otp/verificar', ['codigo' => $wrongCode])
                ->assertUnprocessable()
                ->assertJsonPath('message', 'El código ingresado no es válido.');
        }

        $this->postJson('/otp/verificar', ['codigo' => $wrongCode])
            ->assertStatus(423)
            ->assertJsonPath('bloqueada', true);
        $this->assertTrue($user->fresh()->estaBloqueado());
        $this->assertFalse((bool) $user->fresh()->activo);
        $this->login($user)->assertStatus(423);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_otp_is_not_exposed_in_response_html_url_or_browser_console(): void
    {
        Log::spy();
        $user = $this->createOtpUser();
        $loginResponse = $this->login($user)->assertOk();
        $code = $this->sentCode($user, 0);

        $this->assertStringNotContainsString($code, $loginResponse->getContent());
        $otpPage = $this->get('/otp')->assertOk()->getContent();
        $this->assertStringContainsString('<span id="otp-contador">45</span>', $otpPage);
        $this->assertStringNotContainsString($code, $otpPage);
        $this->assertStringNotContainsString('console.log', $otpPage);
        $this->assertStringNotContainsString($code, route('otp'));

        $this->postJson('/otp/verificar', ['codigo' => $this->differentCode($code)])
            ->assertUnprocessable()
            ->assertJsonMissing(['codigo' => $code]);

        $auditMotives = AuditoriaAcceso::query()->pluck('motivo')->implode(' ');
        $this->assertStringNotContainsString($code, $auditMotives);

        foreach (['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency', 'log'] as $level) {
            Log::shouldNotHaveReceived($level);
        }
    }

    public function test_inactivity_middleware_keeps_the_840_second_boundary(): void
    {
        $this->assertSame(840, config('session.inactivity_timeout'));
        $this->travelTo(Carbon::parse('2026-10-02 12:00:00'));
        $user = $this->createOtpUser();
        $token = $user->createToken('api-token')->accessToken;

        $token->forceFill(['last_used_at' => Carbon::now()->subSeconds(840)])->save();
        $request = Request::create('/api/me', 'GET');
        $request->setUserResolver(fn () => $user->withAccessToken($token));

        $response = app(EnsureSessionNotInactive::class)->handle(
            $request,
            fn () => response()->json(['ok' => true])
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($token->fresh());

        $token->forceFill(['last_used_at' => Carbon::now()->subSeconds(841)])->save();
        $response = app(EnsureSessionNotInactive::class)->handle(
            $request,
            fn () => response()->json(['ok' => true])
        );

        $this->assertSame(401, $response->getStatusCode());
        $this->assertNull($token->fresh());
    }

    public function test_expiry_notice_is_consumed_once_and_not_triggered_by_refresh_url(): void
    {
        $loginPage = $this->get('/login')->assertOk()->getContent();

        $this->assertStringContainsString("sessionStorage.getItem('ges_session_expired_notice') === '1'", $loginPage);
        $this->assertStringContainsString("sessionStorage.removeItem('ges_session_expired_notice')", $loginPage);
        $this->assertStringNotContainsString("searchParams.get('expired')", $loginPage);

        $footer = file_get_contents(resource_path('views/layouts/footer.blade.php'));
        $this->assertSame(2, substr_count($footer, "sessionStorage.setItem('ges_session_expired_notice', '1')"));
        $this->assertStringNotContainsString('?expired=1', $footer);
    }

    private function login(User $user)
    {
        return $this->postJson('/login', [
            'username' => $user->username,
            'password' => 'otp-test-password',
        ]);
    }

    private function createOtpUser(string $roleName = 'Digitadora', ?string $username = null): User
    {
        $role = Rol::create([
            'nombre' => $roleName,
            'descripcion' => 'Usuario de prueba OTP',
        ]);

        foreach (['ver_registros', 'crear_registros', 'editar_registros', 'gestionar_hitos'] as $permission) {
            $permissionId = DB::table('permisos')->where('nombre', $permission)->value('id_permiso')
                ?? DB::table('permisos')->insertGetId([
                    'nombre' => $permission,
                    'descripcion' => $permission,
                ]);
            DB::table('permisos_roles')->insert([
                'id_rol' => $role->id_rol,
                'id_permiso' => $permissionId,
            ]);
        }

        return User::create([
            'nombre' => 'Prueba',
            'apellido' => 'OTP',
            'username' => $username ?? 'otp-'.bin2hex(random_bytes(6)),
            'correo' => 'otp-'.bin2hex(random_bytes(6)).'@example.test',
            'password' => bcrypt('otp-test-password'),
            'id_rol' => $role->id_rol,
            'activo' => true,
        ]);
    }

    private function sentCode(User $user, int $notificationIndex): string
    {
        $notifications = Notification::sent($user, OtpVerificacionNotification::class);
        $notification = $notifications->values()->get($notificationIndex);
        $this->assertNotNull($notification);

        return (new ReflectionProperty(OtpVerificacionNotification::class, 'codigo'))
            ->getValue($notification);
    }

    private function differentCode(string $code): string
    {
        return $code === '000000' ? '000001' : '000000';
    }

    private function createOtpSchema(): void
    {
        Schema::create('roles', function ($table): void {
            $table->id('id_rol');
            $table->string('nombre');
            $table->string('descripcion')->nullable();
        });

        Schema::create('permisos', function ($table): void {
            $table->id('id_permiso');
            $table->string('nombre')->unique();
            $table->string('descripcion')->nullable();
        });

        Schema::create('permisos_roles', function ($table): void {
            $table->unsignedBigInteger('id_rol');
            $table->unsignedBigInteger('id_permiso');
            $table->primary(['id_rol', 'id_permiso']);
        });

        Schema::create('usuarios', function ($table): void {
            $table->id('id_usuario');
            $table->unsignedBigInteger('id_rol')->nullable();
            $table->string('tipo_digitadora')->nullable();
            $table->string('nombre');
            $table->string('apellido');
            $table->string('username')->unique();
            $table->string('correo')->nullable()->unique();
            $table->string('password');
            $table->boolean('activo')->default(true);
            $table->unsignedTinyInteger('intentos_fallidos')->default(0);
            $table->timestamp('bloqueado_en')->nullable();
            $table->timestamp('ultimo_acceso')->nullable();
            $table->timestamp('fecha_creacion')->nullable();
        });

        Schema::create('auditoria_accesos', function ($table): void {
            $table->id('id_auditoria');
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->string('nombre_usuario');
            $table->string('tipo_documento');
            $table->timestamp('fecha_hora_entrada');
            $table->timestamp('fecha_hora_salida')->nullable();
            $table->string('ip')->nullable();
            $table->string('estado');
            $table->text('motivo')->nullable();
        });

        Schema::create('personal_access_tokens', function ($table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }
}
