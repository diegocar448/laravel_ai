<?php

use App\Enums\ReviewPillarEnum;

test('review pillar has 3 cases', function () {
    expect(ReviewPillarEnum::cases())->toHaveCount(3);
    expect(ReviewPillarEnum::Architecture->value)->toBe(1);
    expect(ReviewPillarEnum::Performance->value)->toBe(2);
    expect(ReviewPillarEnum::Security->value)->toBe(3);
});
