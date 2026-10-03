<?php

namespace App\Shared\Observability;

use Illuminate\Log\Logger;
use LogicException;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Handler\ProcessableHandlerInterface;
use Monolog\Logger as MonologLogger;

final class ConfigureSafeLogging
{
    public function __invoke(Logger $logger): void
    {
        $backend = $logger->getLogger();
        if (! $backend instanceof MonologLogger) {
            throw new LogicException('safe_logger_backend_invalid');
        }
        foreach ($backend->getHandlers() as $handler) {
            if (! $handler instanceof FormattableHandlerInterface || ! $handler instanceof ProcessableHandlerInterface) {
                throw new LogicException('safe_logger_handler_invalid');
            }
            $handler->setFormatter(new JsonFormatter);
            $handler->pushProcessor(new SafeLogProcessor);
        }
    }
}
