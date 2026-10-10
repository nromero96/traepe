<?php

use App\Modules\Identity\Application\LocalOtp;
use App\Modules\Identity\Domain\LocalConsent;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = getenv('TRAEPE_TEST_DATABASE');
if (! is_string($database) || ! preg_match('/^traepe_00e_test_[a-f0-9]{16}$/D', $database)) {
    exit(1);
}
$app->detectEnvironment(fn () => 'testing');
config(['database.default' => 'pgsql', 'database.connections.pgsql.database' => $database]);
DB::purge('pgsql');
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$id = app(LocalOtp::class)->verify($input['challenge'], $input['code'], 'Concurrent Local Test', new LocalConsent(true));
echo $id === null ? 'rejected' : 'accepted';
