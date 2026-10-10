<?php

use App\Mcp\Registry\ToolRegistry;

/**
 * MCP parity (spec §4.3): every public service method is exposed as a tool or
 * listed in mcp/EXCLUDED.md with a reason.
 *
 * @return list<string> "Class::method" ids of every public service method
 */
function serviceMethods(): array
{
    $ids = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Services')));

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $relative = str_replace([app_path().'/', '.php', '/'], ['', '', '\\'], $file->getPathname());
        $class = 'App\\'.$relative;
        $reflection = new ReflectionClass($class);

        if ($reflection->isInterface() || $reflection->isEnum() || $reflection->isAbstract() || $reflection->isTrait()) {
            continue;
        }

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (str_starts_with($method->getName(), '__')) {
                continue;
            }

            $ids[] = "{$class}::{$method->getName()}";
        }
    }

    sort($ids);

    return $ids;
}

/**
 * @return list<string> patterns from mcp/EXCLUDED.md
 */
function excludedPatterns(): array
{
    preg_match_all('/^- `([^`]+)`/m', (string) file_get_contents(base_path('mcp/EXCLUDED.md')), $matches);

    return $matches[1];
}

function patternMatches(string $pattern, string $id): bool
{
    $quoted = preg_quote($pattern, '/');
    $regex = '/^'.str_replace(['\\*'], ['.*'], $quoted).'$/';

    return preg_match($regex, $id) === 1;
}

it('exposes every public service method as a tool or excludes it with a reason', function () {
    $covered = app(ToolRegistry::class)->definitions()->flatMap(fn ($definition) => $definition->covers)->unique()->all();
    $patterns = excludedPatterns();

    $uncovered = array_values(array_filter(serviceMethods(), function (string $id) use ($covered, $patterns): bool {
        if (in_array($id, $covered, true)) {
            return false;
        }

        foreach ($patterns as $pattern) {
            if (patternMatches($pattern, $id)) {
                return false;
            }
        }

        return true;
    }));

    expect($uncovered)->toBe([], "These service methods are neither an MCP tool nor listed in mcp/EXCLUDED.md:\n".implode("\n", $uncovered));
});

it('only claims coverage of service methods that exist', function () {
    $existing = serviceMethods();

    foreach (app(ToolRegistry::class)->definitions() as $definition) {
        foreach ($definition->covers as $id) {
            expect(in_array($id, $existing, true))->toBeTrue("{$definition->name} covers {$id}, which does not exist");
        }
    }
});

it('has no stale exclusion entries', function () {
    $existing = serviceMethods();

    foreach (excludedPatterns() as $pattern) {
        $matched = array_filter($existing, fn (string $id): bool => patternMatches($pattern, $id));

        expect($matched !== [])->toBeTrue("mcp/EXCLUDED.md lists `{$pattern}`, which matches no service method");
    }
});

it('does not exclude something that is also a tool', function () {
    $covered = app(ToolRegistry::class)->definitions()->flatMap(fn ($definition) => $definition->covers)->unique()->all();

    foreach (excludedPatterns() as $pattern) {
        if (str_contains($pattern, '*')) {
            continue;
        }

        expect(in_array($pattern, $covered, true))->toBeFalse("`{$pattern}` is both excluded and exposed");
    }
});

it('gives every tool a unique snake_case domain_verb_object name', function () {
    $names = app(ToolRegistry::class)->definitions()->keys();

    expect($names->unique()->count())->toBe($names->count());

    foreach ($names as $name) {
        expect($name)->toMatch('/^[a-z]+(_[a-z]+)+$/');
    }
});
