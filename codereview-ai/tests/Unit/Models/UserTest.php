<?php

use App\Models\User;

test('user has correct fillable attributes', function () {
    $user = new User;

    expect($user->getFillable())->toContain(
        'name', 'email', 'password', 'is_admin', 'first_review_at', 'first_plan_at'
    );
});

test('user casts password as hashed', function () {
    $user = new User;
    $casts = $user->getCasts();

    expect($casts['password'])->toBe('hashed');
    expect($casts['is_admin'])->toBe('boolean');
    expect($casts['first_review_at'])->toBe('datetime');
});

test('user hides sensitive fields', function () {
    $user = new User;

    expect($user->getHidden())->toContain('password', 'remember_token');
});
