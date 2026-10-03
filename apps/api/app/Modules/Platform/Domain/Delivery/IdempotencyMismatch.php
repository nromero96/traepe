<?php

namespace App\Modules\Platform\Domain\Delivery;

use RuntimeException;

final class IdempotencyMismatch extends RuntimeException {}
