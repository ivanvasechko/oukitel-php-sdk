<?php

declare(strict_types=1);

it('keeps production code framework-independent with mixed guards at parsing boundaries', function (): void {
    $root = dirname(__DIR__).'/src';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    $guards = ['is_array', 'is_string', 'is_int', 'is_bool', 'is_float', 'is_numeric', 'is_object', 'is_scalar', 'is_null'];
    $count = 0;
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $count++;
        $path = $file->getPathname();
        $source = file_get_contents($path);
        expect($source)->not->toMatch('/\b(?:Illuminate|Laravel)\\\\/');
        if (! str_contains($path, '/Testing/')) {
            expect($source)->not->toContain('IvanVasechko\\Oukitel\\Testing\\');
        }
        if (str_contains($path, '/Parsing/') || str_ends_with($path, '/Config/RegionConfiguration.php')) {
            continue;
        }
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED], true)) {
                expect(in_array(strtolower(ltrim($token[1], '\\')), $guards, true))
                    ->toBeFalse('Mixed-data guard outside a parsing boundary: '.$path);
            }
        }
    }
    expect($count)->toBeGreaterThan(0);
});

it('ships only canonical protocol fixture envelopes without observation histories', function (): void {
    foreach (glob(dirname(__DIR__).'/src/Testing/Fixtures/*.json') as $path) {
        $fixture = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        expect(array_keys($fixture))->toBe(['code', 'data'])
            ->and($fixture['code'])->toBe(200)
            ->and(array_keys($fixture['data']))->toBe(['deviceData', 'customizeTslInfo']);
    }
});
