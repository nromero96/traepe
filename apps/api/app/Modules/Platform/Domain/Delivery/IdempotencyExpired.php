<?php

namespace App\Modules\Platform\Domain\Delivery;

use RuntimeException;

final class IdempotencyExpired extends RuntimeException {}
