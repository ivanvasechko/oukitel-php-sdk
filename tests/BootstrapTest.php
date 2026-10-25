<?php

declare(strict_types=1);

it('declares an unofficial noncommercial package without framework dependencies', function (): void {
    $manifest = json_decode(file_get_contents(dirname(__DIR__).'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['name'])->toBe('ivanvasechko/oukitel-php-sdk')
        ->and($manifest['license'])->toBe('PolyForm-Noncommercial-1.0.0')
        ->and($manifest['description'])->toStartWith('Unofficial')
        ->and(array_keys($manifest['require']))->toBe(['php']);
});

it('ships its licence and required notice', function (): void {
    $root = dirname(__DIR__);

    expect(file_get_contents($root.'/LICENSE'))
        ->toStartWith('# PolyForm Noncommercial License 1.0.0')
        ->toContain('Required Notice: Copyright (c) Ivan Vasechko')
        ->and(file_get_contents($root.'/NOTICE'))->toContain('No code from that project is included');
});
