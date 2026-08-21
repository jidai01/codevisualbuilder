<?php

namespace App\Services\Compiler\Pipeline\Steps;

use App\Services\Compiler\DependencySorter;
use Closure;

class SortDependencies
{
    protected DependencySorter $sorter;

    public function __construct(DependencySorter $sorter)
    {
        $this->sorter = $sorter;
    }

    public function handle(array $payload, Closure $next): array
    {
        $payload['sorted_entities'] = $this->sorter->sort($payload['blueprint']['entities']);

        return $next($payload);
    }
}
