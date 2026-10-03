<?php

namespace App\Modules\Platform\Application\Scaffolding;

interface ModuleGenerator
{
    public const MODULES = ['Identity', 'Marketplace', 'Catalog', 'Pricing', 'Ordering', 'Payments', 'Logistics', 'Settlements', 'Support', 'Engagement', 'Platform', 'DataAI'];

    public function generate(string $name): string;
}
