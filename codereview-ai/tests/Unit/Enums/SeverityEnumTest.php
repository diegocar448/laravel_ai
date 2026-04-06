<?php

use App\Enums\SeverityEnum;

test('severity enum has correct values', function () {
    expect(SeverityEnum::Low->value)->toBe('low');
    expect(SeverityEnum::Medium->value)->toBe('medium');
    expect(SeverityEnum::High->value)->toBe('high');
    expect(SeverityEnum::Critical->value)->toBe('critical');
});

test('severity enum has 4 cases', function () {
    expect(SeverityEnum::cases())->toHaveCount(4);
});

test('severity can be created from string', function () {
    expect(SeverityEnum::from('critical'))->toBe(SeverityEnum::Critical);
});

test('severity tryFrom returns null for invalid value', function () {
    expect(SeverityEnum::tryFrom('invalid'))->toBeNull();
});
