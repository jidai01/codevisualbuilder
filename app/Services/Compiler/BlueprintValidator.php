<?php

namespace App\Services\Compiler;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BlueprintValidator
{
    public function validate(array $blueprint): array
    {
        $validator = Validator::make($blueprint, [
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
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $entityNames = array_column($blueprint['entities'], 'name');
        $duplicates = array_diff_key($entityNames, array_unique($entityNames));

        if (!empty($duplicates)) {
            throw ValidationException::withMessages([
                'entities' => ['Duplicate entity names found: ' . implode(', ', array_unique($duplicates))],
            ]);
        }

        return $blueprint;
    }
}
