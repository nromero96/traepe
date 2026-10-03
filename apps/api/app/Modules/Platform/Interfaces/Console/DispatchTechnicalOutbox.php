<?php

namespace App\Modules\Platform\Interfaces\Console;

use App\Modules\Platform\Application\Delivery\OutboxDispatchRequest;
use Illuminate\Console\Command;

final class DispatchTechnicalOutbox extends Command
{
    protected $signature = 'platform:dispatch-outbox {--limit=100}';

    protected $description = 'Publicar eventos técnicos pendientes sin crear consumidores comerciales';

    public function handle(OutboxDispatchRequest $dispatcher): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($limit === false) {
            $this->error('limit debe ser un entero entre 1 y 1000.');

            return self::FAILURE;
        }
        $dispatcher->enqueue($limit);
        $this->info('Lote técnico encolado. La publicación será procesada por Horizon.');

        return self::SUCCESS;
    }
}
