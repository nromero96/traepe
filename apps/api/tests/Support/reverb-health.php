<?php

use App\Modules\Platform\Infrastructure\Health\ReverbHealth;

require __DIR__.'/../../app/Modules/Platform/Infrastructure/Health/ReverbHealth.php';

exit(ReverbHealth::check('127.0.0.1', 8080, (string) getenv('REVERB_APP_KEY')) ? 0 : 1);
