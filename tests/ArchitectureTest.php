<?php

declare(strict_types=1);
use IvanVasechko\Oukitel\Controls\OukitelControls;

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

it('ships only synthetic fixture envelopes with minimal example metadata', function (): void {
    $codes = [
        'battery_percentage', 'remain_time', 'remain_charging_time', 'total_input_power',
        'total_output_power', 'ac_input', 'dc_input', 'temp', 'inverter_temp', 'ac_switch',
        'AC_Info', 'DC_Info', 'USB_Info', 'TypeC_Info', 'ac_data', 'dc_data', 'usb_data',
        'typec_data', 'usb_switch', 'dc_switch', 'high_frequency_reporting', 'ac_charging_limit',
    ];
    foreach (glob(dirname(__DIR__).'/src/Testing/Fixtures/*.json') as $path) {
        $fixture = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        expect(array_keys($fixture))->toBe(['code', 'data'])
            ->and($fixture['code'])->toBe(200)
            ->and(array_keys($fixture['data']))->toBe(['deviceData', 'customizeTslInfo']);
        expect(array_keys($fixture['data']['deviceData']))->toBe(['isOnline', 'signalStrength']);
        foreach ($fixture['data']['customizeTslInfo'] as $attribute) {
            expect(array_keys($attribute))->toBe(['resourceCode', 'resourceValce', 'dataType']);
            expect(in_array($attribute['resourceCode'], $codes, true))->toBeTrue();
        }
    }
});

it('keeps experimental controls outside session and parsing implementation paths', function () {
    foreach (['Session', 'Parsing'] as $directory) {
        foreach (glob(dirname(__DIR__).'/src/'.$directory.'/*.php') as $path) {
            expect(file_get_contents($path))->not->toContain('IvanVasechko\\Oukitel\\Controls\\');
        }
    }
});

it('marks every control API class and method experimental', function () {
    $class = new ReflectionClass(OukitelControls::class);
    expect($class->getDocComment())->toContain('@experimental');
    foreach ($class->getMethods() as $method) {
        expect($method->getDocComment())->toContain('@experimental');
    }
});

it('excludes development documentation from archives and keeps validation records out of source', function () {
    $root = dirname(__DIR__);

    expect(file_get_contents($root.'/.gitattributes'))->toContain('/docs export-ignore');
    expect(glob($root.'/docs/validation/*'))->toBe([]);
});
