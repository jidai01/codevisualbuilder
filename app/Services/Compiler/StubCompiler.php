<?php

namespace App\Services\Compiler;

use Illuminate\Filesystem\Filesystem;

class StubCompiler
{
    protected Filesystem $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function compile(string $stubPath, array $variables, string $outputPath): string
    {
        $stub = $this->filesystem->get($stubPath);

        foreach ($variables as $key => $value) {
            $stub = str_replace("{{ {$key} }}", $value, $stub);
        }

        $this->filesystem->put($outputPath, $stub);

        return $outputPath;
    }

    public function compileString(string $stub, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $stub = str_replace("{{ {$key} }}", $value, $stub);
        }

        return $stub;
    }
}
