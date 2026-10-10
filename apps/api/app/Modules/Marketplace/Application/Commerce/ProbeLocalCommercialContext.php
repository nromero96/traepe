<?php

namespace App\Modules\Marketplace\Application\Commerce;

use App\Modules\Marketplace\Domain\Commerce\CommercialContext;

final readonly class ProbeLocalCommercialContext
{
    public const VERSION = 'local-commercial-context-v1';

    public function __construct(private LocalCommercialContextSource $source) {}

    public function evaluate(CommercialContext $context): bool
    {
        return $this->source->matches($context);
    }
}
