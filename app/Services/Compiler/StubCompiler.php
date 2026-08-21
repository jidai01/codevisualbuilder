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
        $compiled = $this->compileString($stub, $variables);

        $this->filesystem->makeDirectory(dirname($outputPath), 0755, true, true);
        $this->filesystem->put($outputPath, $compiled);

        return $outputPath;
    }

    public function compileString(string $stub, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $stub = str_replace("{{ {$key} }}", (string) $value, $stub);
        }

        $stub = $this->cleanEmptyLines($stub);

        return $stub;
    }

    public function compileBlock(string $stub, string $blockName, array $items, callable $callback): string
    {
        $pattern = '/@foreach\(\s*' . preg_quote($blockName) . '\s*\)(.*?)@endforeach/s';

        if (preg_match($pattern, $stub, $matches)) {
            $template = $matches[1];
            $result = '';

            foreach ($items as $index => $item) {
                $rendered = $callback($item, $index);
                $result .= str_replace('{{ loop_body }}', $rendered, $template);
            }

            $stub = preg_replace($pattern, rtrim($result), $stub);
        }

        return $stub;
    }

    protected function cleanEmptyLines(string $content): string
    {
        $lines = explode("\n", $content);
        $cleaned = [];
        $prevEmpty = false;

        foreach ($lines as $line) {
            $trimmed = rtrim($line);

            if ($trimmed === '' && $prevEmpty) {
                continue;
            }

            $cleaned[] = $line;
            $prevEmpty = ($trimmed === '');
        }

        return implode("\n", $cleaned);
    }
}
