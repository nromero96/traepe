<?php

namespace Tests\Integration;

use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogAccess;
use App\Modules\Marketplace\Application\Commerce\LocalDraftCommerceAccess;
use App\Modules\Marketplace\Application\Coverage\LocalPersistedCoverageAccess;
use App\Modules\Marketplace\Application\Fixtures\LocalDraftFixtureAccess;
use App\Modules\Marketplace\Domain\Fixtures\FixtureProfile;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Process;
use Throwable;

final class LocalIdentityHttpTest extends PostgresTestCase
{
    private const COOKIE = 'identity-http-test-session';

    private ?Process $server = null;

    private ?string $directory = null;

    private string $origin;

    private CookieJar $cookies;

    private Client $client;

    private function startServer(): void
    {
        $this->assertTrue(is_string($this->probeDatabase) && preg_match('/^traepe_00e_test_[a-f0-9]{16}$/D', $this->probeDatabase) === 1);
        $this->directory = sys_get_temp_dir().'/traepe_identity_http_'.bin2hex(random_bytes(8));
        foreach (['', '/framework/sessions', '/framework/cache/data', '/framework/views', '/logs'] as $suffix) {
            $this->assertTrue(mkdir($this->directory.$suffix, 0700, true));
        }
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertTrue(is_resource($socket));
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $this->origin = 'http://'.$address;
        $this->cookies = new CookieJar;
        $this->client = $this->clientFor($this->cookies);
        $this->server = new Process([
            PHP_BINARY, '-d', 'variables_order=EGPCS', '-S', $address,
            base_path('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'),
        ], public_path(), [
            'APP_ENV' => 'local', 'APP_DEBUG' => 'false', 'APP_URL' => $this->origin,
            'APP_CONFIG_CACHE' => $this->directory.'/config.php', 'APP_ROUTES_CACHE' => $this->directory.'/routes.php',
            'LARAVEL_STORAGE_PATH' => $this->directory, 'DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => $this->probeDatabase, 'DB_URL' => '',
            'SESSION_DRIVER' => 'file', 'SESSION_ENCRYPT' => 'true', 'SESSION_COOKIE' => self::COOKIE, 'SESSION_LIFETIME' => '120',
            'SESSION_DOMAIN' => 'null', 'SESSION_SECURE_COOKIE' => 'false', 'CACHE_STORE' => 'file',
            'LOG_CHANNEL' => 'safe', 'PHP_CLI_SERVER_WORKERS' => false,
        ]);
        // Access output and response bodies may contain tokens; never print process output.
        $this->server->disableOutput()->setTimeout(null)->start();
        $this->waitForServer();
    }

    private function waitForServer(): void
    {
        $deadline = microtime(true) + 10;
        while (microtime(true) < $deadline && $this->server->isRunning()) {
            try {
                if ($this->client->get('/up', ['cookies' => false])->getStatusCode() === 200) {
                    return;
                }
            } catch (Throwable) {
                // Startup retry only; no exception, credentials or response dump.
            }
            usleep(20_000);
        }
        $this->fail('Isolated Identity HTTP server did not become ready.');
    }

    private function clientFor(CookieJar $cookies): Client
    {
        return new Client([
            'base_uri' => $this->origin, 'cookies' => $cookies, 'http_errors' => false,
            'allow_redirects' => false, 'connect_timeout' => 1, 'timeout' => 5,
            'headers' => ['Accept' => 'application/json'],
        ]);
    }

    private function send(string $method, string $path, int $status, #[\SensitiveParameter] array $payload = [], #[\SensitiveParameter] ?string $token = null, ?Client $client = null, #[\SensitiveParameter] array $headers = []): ResponseInterface
    {
        try {
            $response = ($client ?? $this->client)->request($method, $path, [
                'json' => $payload, 'headers' => $headers + ($token === null ? [] : ['X-XSRF-TOKEN' => $token]),
            ]);
        } catch (Throwable) {
            $this->fail('Isolated Identity HTTP transport failed.');
        }
        // Integer-only assertions keep failure diagnostics free of cookie/body values.
        $this->assertSame($status, $response->getStatusCode());
        if ($path !== '/sanctum/csrf-cookie') {
            $body = $this->body($response);
            $id = $body['meta']['correlation_id'] ?? $body['error']['correlation_id'] ?? null;
            $this->assertTrue(is_string($id) && $id !== '' && $id === $response->getHeaderLine('X-Correlation-ID'));
        }

        return $response;
    }

    private function body(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function token(): string
    {
        $value = $this->cookies->getCookieByName('XSRF-TOKEN')?->getValue();
        $this->assertTrue(is_string($value) && $value !== '');

        return rawurldecode($value);
    }

    private function sessionId(): string
    {
        $value = $this->cookies->getCookieByName(self::COOKIE)?->getValue();
        $this->assertTrue(is_string($value) && $value !== '');
        $id = CookieValuePrefix::remove(Crypt::decryptString(rawurldecode($value)));
        $this->assertTrue(preg_match('/^[A-Za-z0-9]{40}$/D', $id) === 1);

        return $id;
    }

    private function login(string $phone = '+12025550130'): array
    {
        $this->send('GET', '/sanctum/csrf-cookie', 204);
        $challenge = $this->body($this->send('POST', '/api/v1/auth/otp/request', 202, ['phone' => $phone], $this->token()))['data']['id'];
        // Only a test-owned database is read; the interactive delivery command is never bypassed in development.
        $code = Crypt::decryptString(DB::table('identity_otp_challenges')->where('public_id', $challenge)->value('code_ciphertext'));
        $payload = ['challenge_id' => $challenge, 'code' => $code, 'name' => 'HTTP Local Test', 'consent_version' => 'local-v1', 'consent_accepted' => true];
        $before = $this->sessionId();
        $oldToken = $this->token();
        $oldCookies = new CookieJar(false, $this->cookies->toArray());
        $response = $this->send('POST', '/api/v1/auth/otp/verify', 200, $payload, $oldToken);
        $publicId = $this->body($response)['data']['id'];
        $this->assertTrue($before !== $this->sessionId(), 'Login must rotate the underlying session identifier.');
        $this->assertFalse(is_file($this->directory.'/framework/sessions/'.$before));
        $this->assertTrue($this->cookies->getCookieByName(self::COOKIE)->getHttpOnly());
        $this->assertTrue(str_contains(strtolower(implode(';', $response->getHeader('Set-Cookie'))), 'samesite=lax'));
        $this->assertSame(1, DB::table('users')->count());
        $this->assertSame(1, DB::table('identity_consents')->count());
        $this->assertSame(1, DB::table('identity_audit')->where('operation', 'otp_verified')->count());
        $this->assertSame($response->getHeaderLine('X-Correlation-ID'), DB::table('identity_audit')->where('operation', 'otp_verified')->value('correlation_id'));
        $this->assertSame($response->getHeaderLine('X-Correlation-ID'), DB::table('identity_consents')->value('correlation_id'));

        return [$publicId, $payload, $oldToken, $oldCookies];
    }

    public function test_real_csrf_login_rotation_persistence_logout_and_cookie_replay(): void
    {
        $this->startServer();
        $this->send('GET', '/sanctum/csrf-cookie', 204);
        foreach ([null, 'invalid-xsrf'] as $token) {
            $body = $this->body($this->send('POST', '/api/v1/auth/otp/request', 419, ['phone' => '+12025550130'], $token));
            $this->assertSame('csrf_token_mismatch', $body['error']['code']);
            $this->assertSame(0, DB::table('identity_otp_challenges')->count());
        }
        [$publicId, $payload, $oldToken, $oldCookies] = $this->login();
        $this->send('GET', '/api/v1/auth/me', 401, client: $this->clientFor($oldCookies));
        $this->assertSame($publicId, $this->body($this->send('GET', '/api/v1/auth/me', 200))['data']['id']);
        $this->send('GET', '/api/v1/identity/local-authorization-probe', 403);
        $this->send('POST', '/api/v1/auth/logout', 419, token: $oldToken);
        $this->assertSame(0, DB::table('identity_audit')->where('operation', 'session_ended')->count());

        $this->server->stop(2);
        $this->server = $this->server->restart();
        $this->waitForServer();
        $this->send('GET', '/api/v1/auth/me', 200);
        $authenticatedId = $this->sessionId();
        $ciphertext = file_get_contents($this->directory.'/framework/sessions/'.$authenticatedId);
        $this->assertTrue(is_string($ciphertext) && ! str_contains($ciphertext, 'login_web'));
        $this->assertTrue(str_contains(Crypt::decryptString($ciphertext), 'login_web'));
        $authenticatedCookies = new CookieJar(false, $this->cookies->toArray());
        $logout = $this->send('POST', '/api/v1/auth/logout', 200, token: $this->token());
        $this->assertTrue($authenticatedId !== $this->sessionId(), 'Logout must rotate the session identifier.');
        $this->assertFalse(is_file($this->directory.'/framework/sessions/'.$authenticatedId));
        $this->send('GET', '/api/v1/auth/me', 401);
        $this->send('GET', '/api/v1/auth/me', 401, client: $this->clientFor($authenticatedCookies));
        $this->send('GET', '/api/v1/identity/local-authorization-probe', 401, client: $this->clientFor($authenticatedCookies));
        $this->assertSame(1, DB::table('identity_audit')->where('operation', 'session_ended')->count());
        $this->assertSame($logout->getHeaderLine('X-Correlation-ID'), DB::table('identity_audit')->where('operation', 'session_ended')->value('correlation_id'));

        $replayCookies = new CookieJar;
        $replayClient = $this->clientFor($replayCookies);
        $this->send('GET', '/sanctum/csrf-cookie', 204, client: $replayClient);
        $replayToken = rawurldecode($replayCookies->getCookieByName('XSRF-TOKEN')->getValue());
        $this->send('POST', '/api/v1/auth/otp/verify', 422, $payload, $replayToken, $replayClient);
        $this->send('GET', '/api/v1/auth/me', 401, client: $replayClient);
        $this->assertSame(1, DB::table('users')->count());
        $this->assertSame(2, DB::table('identity_audit')->count());
        $this->assertPrivateLogs(['+12025550130', $payload['code'], $oldToken, $authenticatedId, $authenticatedCookies->getCookieByName(self::COOKIE)->getValue()]);
    }

    public function test_real_session_rechecks_blocked_identity_and_rejects_expired_or_tampered_cookies(): void
    {
        $this->startServer();
        [$publicId] = $this->login('+12025550131');
        DB::table('users')->where('public_id', $publicId)->update(['status' => 'blocked']);
        $this->send('GET', '/api/v1/auth/me', 401);
        $this->send('GET', '/api/v1/identity/local-authorization-probe', 403);
        // Restoring the fixture tests expiry independently; no mutation API is introduced.
        DB::table('users')->where('public_id', $publicId)->update(['status' => 'active']);
        $this->send('GET', '/api/v1/auth/me', 200);
        $id = $this->sessionId();
        $this->assertTrue(touch($this->directory.'/framework/sessions/'.$id, time() - 7201));
        $this->send('GET', '/api/v1/auth/me', 401);
        $tampered = CookieJar::fromArray([self::COOKIE => 'invalid-encrypted-session'], '127.0.0.1');
        $this->send('GET', '/api/v1/auth/me', 401, client: $this->clientFor($tampered));
        $this->assertPrivateLogs(['+12025550131', $id]);
    }

    public function test_logout_revokes_the_cookie_even_when_the_audit_write_fails(): void
    {
        $this->startServer();
        $this->login('+12025550132');
        $id = $this->sessionId();
        $token = $this->token();
        $copy = new CookieJar(false, $this->cookies->toArray());
        // Failure injection is restricted to the disposable database created by this test.
        DB::statement("ALTER TABLE identity_audit ADD CONSTRAINT identity_test_reject_session_end CHECK (operation <> 'session_ended')");
        $body = $this->body($this->send('POST', '/api/v1/auth/logout', 500, token: $token));
        $this->assertSame('internal_error', $body['error']['code']);
        $this->assertFalse(is_file($this->directory.'/framework/sessions/'.$id));
        $this->send('GET', '/api/v1/auth/me', 401);
        $this->send('GET', '/api/v1/auth/me', 401, client: $this->clientFor($copy));
        $this->assertSame(0, DB::table('identity_audit')->where('operation', 'session_ended')->count());
        $this->assertSame(1, DB::table('identity_audit')->count());
        $this->assertPrivateLogs(['+12025550132', $id, $token, $copy->getCookieByName(self::COOKIE)->getValue()]);
    }

    public function test_real_cookie_permission_blocking_logout_and_replay_for_persisted_coverage(): void
    {
        $url = '/api/v1/marketplace/local-persisted-coverage-probe';
        $this->startServer();
        $this->send('GET', $url, 401);
        [$actor] = $this->login('+12025550133');
        $this->send('GET', $url.'?market_public_id=invalid&longitude=invalid', 403);
        $permission = DB::table('identity_permissions')->insertGetId(['public_id' => (string) Str::ulid(), 'code' => LocalPersistedCoverageAccess::CAPABILITY]);
        $user = DB::table('users')->where('public_id', $actor)->value('id');
        DB::table('identity_permission_grants')->insert(['public_id' => (string) Str::ulid(), 'user_id' => $user, 'permission_id' => $permission, 'scope_type' => 'platform', 'scope_public_id' => LocalPersistedCoverageAccess::SCOPE_PUBLIC_ID, 'effect' => 'allow']);
        $country = DB::table('countries')->insertGetId(['public_id' => (string) Str::ulid(), 'code' => 'ZZ', 'name' => 'Synthetic Country', 'currency_code' => 'ZZZ']);
        $market = (string) Str::ulid();
        $marketId = DB::table('markets')->insertGetId(['public_id' => $market, 'country_id' => $country, 'name' => 'Synthetic Market', 'timezone' => 'Etc/UTC', 'currency_code' => 'XXX']);
        $zone = (string) Str::ulid();
        DB::insert('INSERT INTO service_zones (public_id, market_id, name, zone_type, polygon, priority) VALUES (?, ?, ?, ?, ST_GeogFromText(?), ?)', [$zone, $marketId, 'Synthetic Zone', 'fixture', 'SRID=4326;MULTIPOLYGON(((0 0,4 0,4 4,0 4,0 0)))', 10]);
        $path = $url.'?'.http_build_query(['market_public_id' => $market, 'longitude' => '0.5', 'latitude' => '0.5']);
        $snapshot = function (): array {
            $hashes = [];
            foreach (['countries', 'markets', 'service_zones', 'users', 'identity_permissions', 'identity_permission_grants'] as $table) {
                $hashes[$table] = hash('sha256', DB::table($table)->orderBy('id')->get()->toJson());
            }

            return $hashes;
        };
        $before = $snapshot();
        $response = $this->send('GET', $path, 200);
        $this->assertSame(['status' => 'selected', 'zone_id' => $zone], $this->body($response)['data']['attributes']);
        $this->assertTrue(str_contains($response->getHeaderLine('Cache-Control'), 'no-store'));
        $this->assertSame($before, $snapshot());
        DB::table('users')->where('id', $user)->update(['status' => 'blocked']);
        $this->send('GET', $path, 403);
        DB::table('users')->where('id', $user)->update(['status' => 'active']);
        $this->send('GET', $path, 200);
        $copy = new CookieJar(false, $this->cookies->toArray());
        $this->send('POST', '/api/v1/auth/logout', 200, token: $this->token());
        $this->send('GET', $path, 401);
        $this->send('GET', $path, 401, client: $this->clientFor($copy));
        $this->assertPrivateLogs(['+12025550133', $path]);
    }

    public function test_real_fixture_creation_csrf_replay_separate_read_permission_revocation_and_logout(): void
    {
        $url = '/api/v1/marketplace/local-draft-fixtures';
        $payload = ['fixture_profile' => FixtureProfile::A->value];
        $headers = ['Idempotency-Key' => 'private-fixture-http-key'];
        $this->startServer();
        $this->send('POST', $url, 419, $payload, headers: $headers);
        $this->send('GET', '/sanctum/csrf-cookie', 204);
        $this->send('POST', $url, 401, $payload, $this->token(), headers: $headers);
        [$actor] = $this->login('+12025550134');
        $this->send('POST', $url, 403, $payload, $this->token(), headers: $headers);
        $permission = DB::table('identity_permissions')->insertGetId(['public_id' => (string) Str::ulid(), 'code' => LocalDraftFixtureAccess::CAPABILITY]);
        $user = DB::table('users')->where('public_id', $actor)->value('id');
        $grant = fn (int $permissionId, string $effect = 'allow') => DB::table('identity_permission_grants')->insertGetId([
            'public_id' => (string) Str::ulid(), 'user_id' => $user, 'permission_id' => $permissionId,
            'scope_type' => 'platform', 'scope_public_id' => LocalDraftFixtureAccess::SCOPE_PUBLIC_ID, 'effect' => $effect,
        ]);
        $grant($permission);
        $this->send('POST', $url, 419, $payload, headers: $headers);
        $this->send('POST', $url, 419, $payload, 'invalid-token', headers: $headers);
        $first = $this->body($this->send('POST', $url, 201, $payload, $this->token(), headers: $headers));
        $second = $this->body($this->send('POST', $url, 201, $payload, $this->token(), headers: $headers));
        $this->assertSame($first['data'], $second['data']);
        $this->assertNotSame($first['meta']['correlation_id'], $second['meta']['correlation_id']);
        $this->assertSame($first['meta']['correlation_id'], DB::table('marketplace_local_fixture_operations')->value('correlation_id'));
        $this->send('POST', $url, 409, ['fixture_profile' => FixtureProfile::B->value], $this->token(), headers: $headers);
        $attributes = $first['data']['attributes'];
        $read = '/api/v1/marketplace/local-persisted-coverage-probe?market_public_id='.$attributes['market_public_id'];
        $this->send('GET', $read.'&longitude=0.5&latitude=0.5', 403);
        $readPermission = DB::table('identity_permissions')->insertGetId(['public_id' => (string) Str::ulid(), 'code' => LocalPersistedCoverageAccess::CAPABILITY]);
        $grant($readPermission);
        foreach ([[0.5, 0.5, 'selected', $attributes['zone_public_ids'][0]], [3.5, 0.5, 'selected', $attributes['zone_public_ids'][1]], [1, 1.5, 'selected', $attributes['zone_public_ids'][0]], [1.5, 1.5, 'outside', null], [5.5, 1.5, 'ambiguous', null]] as [$longitude, $latitude, $status, $zone]) {
            $response = $this->send('GET', $read.'&'.http_build_query(['longitude' => $longitude, 'latitude' => $latitude]), 200);
            $this->assertSame(['status' => $status, 'zone_id' => $zone], $this->body($response)['data']['attributes']);
        }
        $deny = $grant($permission, 'deny');
        $this->send('POST', $url, 403, $payload, $this->token(), headers: $headers);
        DB::table('identity_permission_grants')->where('id', $deny)->delete();
        DB::table('users')->where('id', $user)->update(['status' => 'blocked']);
        $this->send('POST', $url, 403, $payload, $this->token(), headers: $headers);
        DB::table('users')->where('id', $user)->update(['status' => 'active']);
        DB::table('identity_permission_grants')->where('permission_id', $permission)->delete();
        $this->send('POST', $url, 403, $payload, $this->token(), headers: $headers);
        $grant($permission);
        $this->send('POST', $url, 201, $payload, $this->token(), headers: $headers);
        $copy = new CookieJar(false, $this->cookies->toArray());
        $token = $this->token();
        $this->send('POST', '/api/v1/auth/logout', 200, token: $token);
        $this->send('POST', $url, 419, $payload, $token, client: $this->clientFor($copy), headers: $headers);
        $this->send('GET', '/sanctum/csrf-cookie', 204);
        $this->send('POST', $url, 401, $payload, $this->token(), headers: $headers);
        $this->assertSame([1, 1, 3, 1, 1], array_map(fn ($table) => DB::table($table)->count(), ['countries', 'markets', 'service_zones', 'marketplace_local_fixture_operations', 'platform_idempotency_keys']));
        $this->assertPrivateLogs(['+12025550134', 'private-fixture-http-key', FixtureProfile::A->value, $token]);
    }

    public function test_real_commerce_cookie_csrf_create_replay_owned_read_revocation_block_and_logout(): void
    {
        $url = '/api/v1/marketplace/local-draft-commerces';
        $headers = ['Idempotency-Key' => 'private-commerce-http-key'];
        $this->startServer();
        $this->send('POST', $url, 419, [], headers: $headers);
        $this->send('GET', '/sanctum/csrf-cookie', 204);
        $this->send('POST', $url, 401, [], $this->token(), headers: $headers);
        [$actor] = $this->login('+12025550135');
        $this->send('POST', $url, 403, [], $this->token(), headers: $headers);
        $user = DB::table('users')->where('public_id', $actor)->value('id');
        $access = LocalDraftCommerceAccess::class;
        $grant = function (string $capability, string $effect = 'allow') use ($user, $access): int {
            $permission = DB::table('identity_permissions')->where('code', $capability)->value('id') ?? DB::table('identity_permissions')->insertGetId(['public_id' => (string) Str::ulid(), 'code' => $capability]);

            return DB::table('identity_permission_grants')->insertGetId(['public_id' => (string) Str::ulid(), 'user_id' => $user, 'permission_id' => $permission, 'scope_type' => 'platform', 'scope_public_id' => $access::SCOPE_PUBLIC_ID, 'effect' => $effect]);
        };
        $grant($access::CREATE);
        $country = DB::table('countries')->insertGetId(['public_id' => (string) Str::ulid(), 'code' => 'ZZ', 'name' => 'Synthetic Country', 'currency_code' => 'ZZZ']);
        $market = (string) Str::ulid();
        DB::table('markets')->insert(['public_id' => $market, 'country_id' => $country, 'name' => 'Synthetic Market', 'timezone' => 'Etc/UTC', 'currency_code' => 'XXX']);
        $payload = ['merchant' => ['legal_name' => 'Private Synthetic Legal', 'trade_name' => 'Private Synthetic Trade'], 'branch' => ['market_public_id' => $market, 'name' => 'Private Synthetic Branch', 'longitude' => 0.5, 'latitude' => 1.5, 'timezone' => 'Etc/UTC']];
        $this->send('POST', $url, 419, $payload, headers: $headers);
        $this->send('POST', $url, 419, $payload, 'invalid-token', headers: $headers);
        $created = $this->send('POST', $url, 201, $payload, $this->token(), headers: $headers);
        $first = $this->body($created);
        $this->assertTrue(str_contains($created->getHeaderLine('Cache-Control'), 'no-store'));
        $second = $this->body($this->send('POST', $url, 201, $payload, $this->token(), headers: $headers));
        $this->assertSame($first['data'], $second['data']);
        $this->assertNotSame($first['meta']['correlation_id'], $second['meta']['correlation_id']);
        $this->assertSame($first['meta']['correlation_id'], DB::table('marketplace_local_commerce_operations')->value('correlation_id'));
        $mismatch = $payload;
        $mismatch['branch']['name'] = 'Private Synthetic Mismatch';
        $this->send('POST', $url, 409, $mismatch, $this->token(), headers: $headers);
        $read = $url.'/'.$first['data']['id'];
        $this->send('GET', $read, 403);
        $readGrant = $grant($access::READ);
        $this->assertSame($first['data'], $this->body($this->send('GET', $read, 200))['data']);
        $this->send('GET', $url.'/'.(string) Str::ulid(), 404);
        DB::table('identity_permission_grants')->where('id', $readGrant)->delete();
        $this->send('GET', $read, 403);
        $this->send('POST', $url, 201, $payload, $this->token(), headers: $headers);
        $grant($access::READ);
        $deny = $grant($access::CREATE, 'deny');
        $this->send('POST', $url, 403, $payload, $this->token(), headers: $headers);
        $this->send('GET', $read, 200);
        DB::table('identity_permission_grants')->where('id', $deny)->delete();
        DB::table('users')->where('id', $user)->update(['status' => 'blocked']);
        $this->send('POST', $url, 403, $payload, $this->token(), headers: $headers);
        $this->send('GET', $read, 403);
        DB::table('users')->where('id', $user)->update(['status' => 'active']);
        $this->send('POST', $url, 201, $payload, $this->token(), headers: $headers);
        $copy = new CookieJar(false, $this->cookies->toArray());
        $token = $this->token();
        $this->send('POST', '/api/v1/auth/logout', 200, token: $token);
        $this->send('POST', $url, 419, $payload, $token, client: $this->clientFor($copy), headers: $headers);
        $this->send('GET', $read, 401, client: $this->clientFor($copy));
        $this->send('GET', '/sanctum/csrf-cookie', 204);
        $this->send('POST', $url, 401, $payload, $this->token(), headers: $headers);
        $this->assertSame([1, 1, 1, 1], array_map(fn ($table) => DB::table($table)->count(), ['merchants', 'branches', 'marketplace_local_commerce_operations', 'platform_idempotency_keys']));
        $this->assertPrivateLogs(['+12025550135', $headers['Idempotency-Key'], 'Private Synthetic Legal', 'Private Synthetic Trade', 'Private Synthetic Branch', 'Private Synthetic Mismatch', $token]);
    }

    public function test_real_otp_commerce_catalog_chain_csrf_replay_owned_read_revocation_and_logout(): void
    {
        $url = '/api/v1/catalog/local-draft-catalogs';
        $headers = ['Idempotency-Key' => 'private-catalog-http-key'];
        $this->startServer();
        $this->send('POST', $url, 419, [], headers: $headers);
        $this->send('GET', '/sanctum/csrf-cookie', 204);
        $this->send('POST', $url, 401, [], $this->token(), headers: $headers);
        [$actor] = $this->login('+12025550136');
        $this->send('POST', $url, 403, [], $this->token(), headers: $headers);
        $user = DB::table('users')->where('public_id', $actor)->value('id');
        $grant = function (string $capability, string $effect = 'allow') use ($user): int {
            $permission = DB::table('identity_permissions')->where('code', $capability)->value('id') ?? DB::table('identity_permissions')->insertGetId(['public_id' => (string) Str::ulid(), 'code' => $capability]);

            return DB::table('identity_permission_grants')->insertGetId(['public_id' => (string) Str::ulid(), 'user_id' => $user, 'permission_id' => $permission, 'scope_type' => 'platform', 'scope_public_id' => LocalDraftCatalogAccess::SCOPE_PUBLIC_ID, 'effect' => $effect]);
        };
        $grant(LocalDraftCommerceAccess::CREATE);
        $country = DB::table('countries')->insertGetId(['public_id' => (string) Str::ulid(), 'code' => 'ZZ', 'name' => 'Synthetic Country', 'currency_code' => 'ZZZ']);
        $market = (string) Str::ulid();
        DB::table('markets')->insert(['public_id' => $market, 'country_id' => $country, 'name' => 'Synthetic Market', 'timezone' => 'Etc/UTC', 'currency_code' => 'XXX']);
        $commercePayload = ['merchant' => ['legal_name' => 'Private Synthetic Legal', 'trade_name' => 'Private Synthetic Trade'], 'branch' => ['market_public_id' => $market, 'name' => 'Private Synthetic Branch', 'longitude' => 0.5, 'latitude' => 1.5, 'timezone' => 'Etc/UTC']];
        $commerce = $this->body($this->send('POST', '/api/v1/marketplace/local-draft-commerces', 201, $commercePayload, $this->token(), headers: ['Idempotency-Key' => 'private-chain-commerce-key']));
        $payload = ['merchant_public_id' => $commerce['data']['attributes']['merchant']['public_id'], 'catalog' => ['name' => ' Private Synthetic Catalog '], 'product' => ['name' => 'Private Synthetic Product', 'description' => "Private Synthetic Description\nliteral", 'brand' => null]];
        $this->send('POST', $url, 403, $payload, $this->token(), headers: $headers);
        $grant(LocalDraftCatalogAccess::CREATE);
        $this->send('POST', $url, 422, [], $this->token(), headers: $headers);
        $this->send('POST', $url, 415, $payload, $this->token(), headers: $headers + ['Content-Type' => 'text/plain']);
        $missing = $payload;
        $missing['merchant_public_id'] = (string) Str::ulid();
        $this->send('POST', $url, 404, $missing, $this->token(), headers: $headers);
        $this->send('POST', $url, 419, $payload, headers: $headers);
        $this->send('POST', $url, 419, $payload, 'invalid-token', headers: $headers);
        $created = $this->send('POST', $url, 201, $payload, $this->token(), headers: $headers);
        $first = $this->body($created);
        $this->assertTrue(str_contains($created->getHeaderLine('Cache-Control'), 'no-store'));
        $second = $this->body($this->send('POST', $url, 201, $payload, $this->token(), headers: $headers));
        $this->assertSame($first['data'], $second['data']);
        $this->assertNotSame($first['meta']['correlation_id'], $second['meta']['correlation_id']);
        $this->assertSame($first['meta']['correlation_id'], DB::table('catalog_local_draft_operations')->value('correlation_id'));
        $mismatch = $payload;
        $mismatch['product']['description'] = 'Private Synthetic Mismatch';
        $this->send('POST', $url, 409, $mismatch, $this->token(), headers: $headers);
        $read = $url.'/'.$first['data']['id'];
        $this->send('GET', $read, 403);
        $readGrant = $grant(LocalDraftCatalogAccess::READ);
        $this->assertSame($first['data'], $this->body($this->send('GET', $read, 200))['data']);
        $this->send('GET', $url.'/'.(string) Str::ulid(), 404);
        DB::table('identity_permission_grants')->where('id', $readGrant)->delete();
        $this->send('GET', $read, 403);
        $this->send('POST', $url, 201, $payload, $this->token(), headers: $headers);
        $grant(LocalDraftCatalogAccess::READ);
        $deny = $grant(LocalDraftCatalogAccess::CREATE, 'deny');
        $this->send('POST', $url, 403, $payload, $this->token(), headers: $headers);
        $this->send('GET', $read, 200);
        DB::table('identity_permission_grants')->where('id', $deny)->delete();
        DB::table('users')->where('id', $user)->update(['status' => 'blocked']);
        $this->send('POST', $url, 403, $payload, $this->token(), headers: $headers);
        $this->send('GET', $read, 403);
        DB::table('users')->where('id', $user)->update(['status' => 'active']);
        $this->send('POST', $url, 201, $payload, $this->token(), headers: $headers);
        $copy = new CookieJar(false, $this->cookies->toArray());
        $token = $this->token();
        $this->send('POST', '/api/v1/auth/logout', 200, token: $token);
        $this->send('POST', $url, 419, $payload, $token, client: $this->clientFor($copy), headers: $headers);
        $this->send('GET', $read, 401, client: $this->clientFor($copy));
        $this->send('GET', '/sanctum/csrf-cookie', 204);
        $this->send('POST', $url, 401, $payload, $this->token(), headers: $headers);
        $this->assertSame([1, 1, 1, 1, 1, 1, 2], array_map(fn ($table) => DB::table($table)->count(), ['merchants', 'branches', 'marketplace_local_commerce_operations', 'catalogs', 'products', 'catalog_local_draft_operations', 'platform_idempotency_keys']));
        $this->assertPrivateLogs(['+12025550136', $headers['Idempotency-Key'], 'private-chain-commerce-key', 'Private Synthetic Catalog', 'Private Synthetic Product', "Private Synthetic Description\nliteral", 'Private Synthetic Mismatch', 'Private Synthetic Legal', 'Private Synthetic Trade', 'Private Synthetic Branch', $token]);
    }

    private function assertPrivateLogs(#[\SensitiveParameter] array $secrets): void
    {
        $lines = file($this->directory.'/logs/technical.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertTrue(is_array($lines) && count($lines) > 0);
        foreach ($lines as $line) {
            $record = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            $this->assertTrue(array_diff(array_keys($record['context']), ['correlation_id', 'status_code', 'duration_ms', 'exception_type']) === []);
            $this->assertTrue($record['extra'] === [], 'No extra log fields are allowed.');
            $this->assertTrue(in_array($record['message'], ['http.completed', 'http.failed', 'technical.redacted'], true));
        }
        $secrets[] = 'HTTP Local Test';
        foreach ($this->cookies->toArray() as $cookie) {
            $secrets[] = $cookie['Value'];
            $secrets[] = rawurldecode($cookie['Value']);
        }
        $log = implode("\n", $lines);
        foreach (array_unique($secrets) as $secret) {
            $this->assertFalse(str_contains($log, json_encode($secret)), 'Sensitive test data must not appear in logs.');
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->server?->stop(2);
            if ($this->directory !== null) {
                $target = realpath($this->directory);
                if ($target !== $this->directory || ! preg_match('/^'.preg_quote(sys_get_temp_dir(), '/').'\/traepe_identity_http_[a-f0-9]{16}$/D', $target)) {
                    throw new \RuntimeException('Refusing to remove an unexpected test directory.');
                }
                $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, \FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($entries as $entry) {
                    $entry->isDir() && ! $entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
                }
                rmdir($target);
            }
        } finally {
            parent::tearDown();
        }
    }
}
