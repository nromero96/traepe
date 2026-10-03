<?php

namespace App\Modules\Platform\Infrastructure\Health;

final class ReverbHealth
{
    public static function check(string $host, int $port, string $key): bool
    {
        $socket = @fsockopen($host, $port, $error, $message, 1);
        if (! $socket) {
            return false;
        }
        stream_set_timeout($socket, 2);
        fwrite($socket, "GET /app/{$key}?protocol=7&client=technical&version=1.0 HTTP/1.1\r\nHost: localhost\r\nOrigin: http://localhost\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: ".base64_encode(random_bytes(16))."\r\nSec-WebSocket-Version: 13\r\n\r\n");
        $status = fgets($socket);
        fclose($socket);

        return str_contains((string) $status, '101 Switching Protocols');
    }
}
