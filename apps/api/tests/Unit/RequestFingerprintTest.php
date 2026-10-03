<?php

use App\Modules\Platform\Domain\Delivery\RequestFingerprint;

test('mapping order is canonical but array order and primitive types are significant', function () {
    expect(RequestFingerprint::hash(['b' => ['z' => 2, 'a' => 1], 'a' => true]))
        ->toBe(RequestFingerprint::hash(['a' => true, 'b' => ['a' => 1, 'z' => 2]]));
    expect(RequestFingerprint::hash(['values' => [1, 2]]))->not->toBe(RequestFingerprint::hash(['values' => [2, 1]]));
    expect(RequestFingerprint::hash(['value' => 1]))->not->toBe(RequestFingerprint::hash(['value' => '1']));
    expect(RequestFingerprint::hash(['value' => null]))->not->toBe(RequestFingerprint::hash(['value' => false]));
});

test('floats are rejected instead of silently normalized', function () {
    RequestFingerprint::hash(['amount' => 0.1]);
})->throws(InvalidArgumentException::class);
