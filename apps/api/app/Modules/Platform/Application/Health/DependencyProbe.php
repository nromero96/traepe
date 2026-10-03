<?php

namespace App\Modules\Platform\Application\Health;

interface DependencyProbe
{
    public const DEPENDENCIES = ['database', 'cache', 'storage', 'queue', 'realtime', 'mail'];

    public function check(string $dependency): bool;
}
