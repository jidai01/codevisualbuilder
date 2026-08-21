<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Compiler\ProjectGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function __construct(
        protected ProjectGenerator $generator
    ) {}

    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'project' => 'required|string|max:255',
            'entities' => 'required|array|min:1',
        ]);

        try {
            $result = $this->generator->generate($request->only(['project', 'entities']));

            return response()->json($result);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
