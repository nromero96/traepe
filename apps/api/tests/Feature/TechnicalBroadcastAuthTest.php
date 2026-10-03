<?php

namespace Tests\Feature;

use Tests\TestCase;

class TechnicalBroadcastAuthTest extends TestCase
{
    public function test_authentication_scope_and_signature_are_enforced(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $password = str_repeat('fixture-', 5);
        config(['technical.password' => $password,
            'broadcasting.connections.reverb.key' => 'fixture-key',
            'broadcasting.connections.reverb.secret' => 'fixture-secret']);
        $url = '/api/v1/technical/broadcasting/auth';
        $data = ['socket_id' => '1.2', 'channel_name' => 'private-technical.v1'];
        $this->postJson($url, $data)->assertUnauthorized();
        $this->withBasicAuth('technical', $password)->postJson($url, $data)
            ->assertOk()->assertExactJson(['auth' => 'fixture-key:'.hash_hmac('sha256', '1.2:private-technical.v1', 'fixture-secret')]);
        $this->postJson($url, ['socket_id' => '1.2', 'channel_name' => 'private-other.v1'])->assertForbidden();
        $this->postJson($url, ['socket_id' => 'invalid', 'channel_name' => 'private-technical.v1'])->assertUnprocessable();
    }
}
