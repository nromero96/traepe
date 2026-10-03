<?php

use App\Events\TechnicalRealtimeProbe;
use App\Jobs\TechnicalHorizonProbe;
use Aws\S3\Exception\S3Exception;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Horizon\Contracts\JobRepository;

// Run exclusively inside Docker. No credentials or provider exceptions are printed.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function verify(bool $condition, string $label): void
{
    if (! $condition) {
        throw new RuntimeException($label);
    }
    echo "PASS: {$label}\n";
}

function readBytes($socket, int $length): string
{
    $data = '';
    while (strlen($data) < $length) {
        $chunk = fread($socket, $length - strlen($data));
        if ($chunk === false || $chunk === '') {
            throw new RuntimeException('WebSocket read failed');
        }
        $data .= $chunk;
    }

    return $data;
}

function frame($socket): array
{
    $header = readBytes($socket, 2);
    $length = ord($header[1]) & 127;
    if ($length === 126) {
        $length = unpack('n', readBytes($socket, 2))[1];
    }
    if ($length > 10000 || $length === 127) {
        throw new RuntimeException('Unexpected WebSocket frame');
    }

    return json_decode(readBytes($socket, $length), true, flags: JSON_THROW_ON_ERROR);
}

function sendFrame($socket, array $payload): void
{
    $text = json_encode($payload, JSON_THROW_ON_ERROR);
    $mask = random_bytes(4);
    $masked = '';
    for ($i = 0; $i < strlen($text); $i++) {
        $masked .= $text[$i] ^ $mask[$i % 4];
    }
    $length = strlen($text);
    fwrite($socket, "\x81".($length < 126 ? chr($length | 128) : "\xfe".pack('n', $length)).$mask.$masked);
}

function connectWebSocket(): array
{
    $socket = fsockopen('reverb', 8080, $error, $message, 2);
    stream_set_timeout($socket, 3);
    $key = config('broadcasting.connections.reverb.key');
    fwrite($socket, "GET /app/{$key}?protocol=7&client=technical&version=1.0 HTTP/1.1\r\nHost: localhost\r\nOrigin: http://localhost\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: ".base64_encode(random_bytes(16))."\r\nSec-WebSocket-Version: 13\r\n\r\n");
    verify(str_contains((string) fgets($socket), '101 Switching Protocols'), 'Reverb protocol handshake');
    while (($line = fgets($socket)) !== "\r\n") {
        if ($line === false) {
            throw new RuntimeException('WebSocket upgrade failed');
        }
    }
    $established = frame($socket);
    verify($established['event'] === 'pusher:connection_established', 'Reverb connection established');

    return [$socket, json_decode($established['data'], true)['socket_id']];
}

try {
    $mode = $argv[1] ?? 'smoke';
    if ($mode === 'queue-unavailable') {
        try {
            dispatch(new TechnicalHorizonProbe('technical:unavailable:'.bin2hex(random_bytes(8))));
        } catch (Throwable) {
            echo "PASS: queue dispatch detects unavailable Redis without printing provider details\n";
            exit(0);
        }
        throw new RuntimeException('Unavailable queue unexpectedly accepted dispatch');
    }
    if ($mode === 'migrations') {
        $database = 'traepe_00c_probe_'.bin2hex(random_bytes(6));
        $original = DB::connection('pgsql');
        $original->statement('CREATE DATABASE "'.$database.'"');
        try {
            config(['database.connections.probe' => array_replace(config('database.connections.pgsql'), ['database' => $database])]);
            verify(Artisan::call('migrate', ['--database' => 'probe', '--force' => true]) === 0, 'migrations on isolated clean database');
            verify(Artisan::call('migrate', ['--database' => 'probe', '--force' => true]) === 0 && str_contains(Artisan::output(), 'Nothing to migrate'), 'second migration is idempotent');
        } finally {
            DB::purge('probe');
            $original->statement('DROP DATABASE "'.$database.'"');
        }
        exit(0);
    }
    $disk = Storage::disk('s3');
    $persistencePath = 'technical/00c-persistence.txt';
    if ($mode === 'persist-write') {
        $marker = 'technical:persistence:'.bin2hex(random_bytes(12));
        Cache::store('redis')->put($marker, 'persisted', 600);
        Mail::raw('Technical persistence probe.', fn ($mail) => $mail->to('probe@traepe.test')->subject($marker));
        verify($disk->put($persistencePath, json_encode([
            'marker' => $marker, 'migrations' => DB::table('migrations')->count(),
        ], JSON_THROW_ON_ERROR)), 'persistence object written');
        exit(0);
    }
    if ($mode === 'persist-read') {
        $state = json_decode($disk->get($persistencePath), true, flags: JSON_THROW_ON_ERROR);
        verify(str_starts_with($state['marker'], 'technical:persistence:'), 'object persisted after restart');
        verify(Cache::store('redis')->get($state['marker']) === 'persisted', 'Redis marker persisted after restart');
        verify(DB::table('migrations')->count() === $state['migrations'], 'PostgreSQL migration state persisted after restart');
        $messages = Http::get('http://mailpit:8025/api/v1/messages')->json('messages');
        verify(collect($messages)->contains(fn ($mail) => $mail['Subject'] === $state['marker']), 'Mailpit message persisted after restart');
        Cache::store('redis')->forget($state['marker']);
        verify($disk->delete($persistencePath), 'persistence probe removed');
        exit(0);
    }
    $id = bin2hex(random_bytes(12));
    $key = 'technical:00c:'.$id;
    $cache = Cache::store('redis');
    if ($mode === 'failed-job') {
        dispatch(new TechnicalHorizonProbe($key, true, 1));
        $deadline = microtime(true) + 15;
        do {
            usleep(200000);
            $failed = DB::table('failed_jobs')->where('payload', 'like', '%'.$key.'%')->first();
        } while (! $failed && microtime(true) < $deadline);
        verify($failed !== null, 'controlled terminal failure recorded');
        $job = app(JobRepository::class)->getJobs([$failed->uuid])->first();
        verify($job !== null && $job->status === 'failed', 'failed job observable in Horizon');
        verify(Artisan::call('queue:retry', ['id' => [$failed->uuid]]) === 0, 'explicit retry accepted');
        $deadline = microtime(true) + 15;
        do {
            usleep(200000);
        } while ($cache->get($key) !== 'processed' && microtime(true) < $deadline);
        verify($cache->get($key) === 'processed', 'explicit retry processed successfully');
        $cache->forget($key);
        $cache->forget($key.':first-attempt');
        exit(0);
    }
    if ($mode === 'queue') {
        foreach ([false, true] as $failOnce) {
            $jobKey = $key.($failOnce ? ':retry' : ':success');
            dispatch(new TechnicalHorizonProbe($jobKey, $failOnce));
            $deadline = microtime(true) + 25;
            do {
                usleep(200000);
            } while ($cache->get($jobKey) !== 'processed' && microtime(true) < $deadline);
            verify($cache->get($jobKey) === 'processed', $failOnce ? 'Horizon controlled retry consumed' : 'Horizon real job consumed');
            $redeliveryId = Queue::connection('redis')->push(new TechnicalHorizonProbe($jobKey));
            $deadline = microtime(true) + 15;
            do {
                usleep(200000);
                $redelivery = app(JobRepository::class)->getJobs([$redeliveryId])->first();
            } while ((! $redelivery || $redelivery->status !== 'completed') && microtime(true) < $deadline);
            verify($redelivery !== null && $redelivery->status === 'completed', 'redelivery actually consumed by Horizon');
            verify($cache->get($jobKey) === 'processed', 'redelivery preserves observable value');
            $cache->forget($jobKey);
            $cache->forget($jobKey.':first-attempt');
        }
        exit(0);
    }
    if ($mode === 'websocket') {
        [$socket] = connectWebSocket();
        sendFrame($socket, ['event' => 'pusher:subscribe', 'data' => ['channel' => 'private-technical.v1', 'auth' => 'invalid']]);
        verify(frame($socket)['event'] === 'pusher:error', 'private subscription rejects invalid signature');
        fclose($socket);
        for ($connection = 0; $connection < 2; $connection++) {
            [$socket, $socketId] = connectWebSocket();
            $authorization = Http::withBasicAuth('technical', config('technical.password'))->post('http://nginx/api/v1/technical/broadcasting/auth', ['socket_id' => $socketId, 'channel_name' => 'private-technical.v1']);
            verify($authorization->successful(), 'private channel HTTP authorization');
            sendFrame($socket, ['event' => 'pusher:subscribe', 'data' => ['channel' => 'private-technical.v1', 'auth' => $authorization->json('auth')]]);
            verify(frame($socket)['event'] === 'pusher_internal:subscription_succeeded', 'private subscription accepted');
            $probeId = $id.'-'.$connection;
            event(new TechnicalRealtimeProbe($probeId));
            $received = frame($socket);
            $payload = is_string($received['data']) ? json_decode($received['data'], true) : $received['data'];
            verify($received['event'] === 'technical.probe.v1' && $payload === ['version' => 1, 'probe_id' => $probeId], 'versioned private event received');
            fclose($socket);
        }
        echo "PASS: disconnect and reconnect\n";
        exit(0);
    }
    verify(DB::selectOne('SELECT PostGIS_Version() AS version')->version !== '', 'PostGIS query');
    $cache->put($key, 'value', 60);
    verify($cache->get($key) === 'value' && $cache->forget($key), 'Redis cache write/read/delete');
    $path = 'technical/'.$id.'.txt';
    verify($disk->put($path, 'technical-object'), 'S3 write');
    verify($disk->get($path) === 'technical-object', 'S3 read');
    verify(Http::get('http://minio:9000/traepe-local/'.$path)->status() === 403, 'S3 object denies anonymous access');
    try {
        $disk->getClient()->createBucket(['Bucket' => 'traepe-denied-'.$id]);
        $disk->getClient()->deleteBucket(['Bucket' => 'traepe-denied-'.$id]);
        throw new RuntimeException('Application credential can create unrelated buckets');
    } catch (S3Exception $error) {
        verify($error->getStatusCode() === 403, 'S3 application credential is restricted');
    }
    verify($disk->delete($path) && ! $disk->exists($path), 'S3 delete');
    Mail::raw('Technical infrastructure probe, no personal data.', fn ($mail) => $mail->to('probe@traepe.test')->subject('traepe-00c-'.$id));
    $messages = Http::get('http://mailpit:8025/api/v1/messages')->json('messages');
    verify(collect($messages)->contains(fn ($mail) => $mail['Subject'] === 'traepe-00c-'.$id), 'Mailpit received Laravel SMTP message');
} catch (Throwable $error) {
    fwrite(STDERR, 'Infrastructure probe failed ('.get_class($error)."). No provider details printed.\n");
    exit(1);
}
