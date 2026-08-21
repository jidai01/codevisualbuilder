<?php

namespace App\Services\Compiler\Pipeline\Steps;

use Closure;

class ValidateBlueprint
{
    public function handle(array $payload, Closure $next): array
    {
        $validator = \Illuminate\Support\Facades\Validator::make($payload['blueprint'], [
            'project' => 'required|string|max:255',
            'entities' => 'required|array|min:1',
            'entities.*.name' => 'required|string|max:255',
            'entities.*.fields' => 'required|array|min:1',
            'entities.*.fields.*.name' => 'required|string|max:255',
            'entities.*.fields.*.type' => 'required|string|in:string,integer,bigIncrements,bigInteger,text,boolean,datetime,decimal,float,json,timestamps,softDeletes',
            'entities.*.fields.*.nullable' => 'sometimes|boolean',
            'entities.*.fields.*.default' => 'sometimes|string',
            'entities.*.fields.*.unique' => 'sometimes|boolean',
            'entities.*.fields.*.index' => 'sometimes|boolean',
            'entities.*.fields.*.unsigned' => 'sometimes|boolean',
            'entities.*.relations' => 'sometimes|array',
            'entities.*.relations.*.type' => 'required_with:entities.*.relations|in:belongsTo,hasMany,hasOne,belongsToMany',
            'entities.*.relations.*.target' => 'required_with:entities.*.relations|string',
            'entities.*.relations.*.foreignKey' => 'sometimes|string',
            'entities.*.relations.*.pivotTable' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $entityNames = array_column($payload['blueprint']['entities'], 'name');
        $duplicates = array_diff_key($entityNames, array_unique($entityNames));

        if (!empty($duplicates)) {
            throw new \Illuminate\Validation\ValidationException(
                $validator->errors()->add('entities', 'Duplicate entity names: ' . implode(', ', array_unique($duplicates)))
            );
        }

        $payload['validated'] = true;

        return $next($payload);
    }
}
