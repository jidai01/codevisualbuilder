<?php

namespace App\Services\Compiler;

use RuntimeException;

class DependencySorter
{
    public function sort(array $entities): array
    {
        $graph = [];
        $inDegree = [];
        $entityMap = [];

        foreach ($entities as $entity) {
            $name = $entity['name'];
            $entityMap[$name] = $entity;
            $graph[$name] = [];
            $inDegree[$name] = 0;
        }

        foreach ($entities as $entity) {
            $name = $entity['name'];

            foreach ($entity['relations'] ?? [] as $relation) {
                $target = $relation['target'] ?? null;
                $type = $relation['type'] ?? null;

                if ($target === null) {
                    continue;
                }

                if (!isset($graph[$target])) {
                    throw new RuntimeException(
                        "Entity '{$name}' references unknown target '{$target}'."
                    );
                }

                if (!isset($graph[$name])) {
                    $graph[$name] = [];
                }

                if ($type === 'belongsTo') {
                    $graph[$target][] = $name;
                    $inDegree[$name]++;
                }
            }
        }

        $queue = [];
        foreach ($inDegree as $entity => $degree) {
            if ($degree === 0) {
                $queue[] = $entity;
            }
        }

        $sorted = [];
        while (!empty($queue)) {
            $current = array_shift($queue);
            $sorted[] = $entityMap[$current];

            foreach ($graph[$current] as $neighbor) {
                $inDegree[$neighbor]--;

                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        if (count($sorted) !== count($entities)) {
            throw new RuntimeException(
                'Circular dependency detected among entities: ' .
                implode(', ', array_diff(array_keys($entityMap), array_column($sorted, 'name')))
            );
        }

        return $sorted;
    }
}
