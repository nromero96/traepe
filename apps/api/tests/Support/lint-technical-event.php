<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use JsonSchema\Validator;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
Phar::loadPhar('/usr/local/bin/composer', 'composer.phar');
require 'phar://composer.phar/vendor/autoload.php';

try {
    $ciphertext = DB::table('platform_outbox_messages')->orderByDesc('id')->value('content_ciphertext');
    if (! $ciphertext) {
        throw new RuntimeException('Run the technical delivery probe first.');
    }
    $event = json_decode(Crypt::decryptString($ciphertext), flags: JSON_THROW_ON_ERROR);
    $schema = json_decode(file_get_contents('/var/www/docs/api/schemas/platform-technical-probe.v1.json'), flags: JSON_THROW_ON_ERROR);
    $validator = new Validator;
    $validator->validate($event, $schema);
    if (! $validator->isValid()) {
        throw new RuntimeException('Technical contract failed.');
    }
    $invalid = clone $event;
    unset($invalid->version);
    $negative = new Validator;
    $negative->validate($invalid, $schema);
    if ($negative->isValid()) {
        throw new RuntimeException('Invalid event accepted.');
    }
    echo "PASS: persisted encrypted technical event matches v1 schema; malformed event rejected.\n";
} catch (Throwable) {
    fwrite(STDERR, "Technical event validation failed; payload/provider details suppressed.\n");
    exit(1);
}
