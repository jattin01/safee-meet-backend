<?php

use App\Support\Phone\PhoneNumberNormalizer;

it('keeps numbers that already have an Indian or US country code', function (string $input, string $expected) {
    expect(PhoneNumberNormalizer::normalize($input))->toBe($expected);
})->with([
    'india with plus' => ['+919876543210', '+919876543210'],
    'india formatted' => ['+91 98765-43210', '+919876543210'],
    'india without plus' => ['919876543210', '+919876543210'],
    'us with plus' => ['+12025550123', '+12025550123'],
    'us formatted' => ['+1 (202) 555-0123', '+12025550123'],
    'us without plus' => ['12025550123', '+12025550123'],
]);

it('adds the country code from the user registered phone', function (string $input, ?string $userPhone, string $expected) {
    expect(PhoneNumberNormalizer::normalize($input, $userPhone))->toBe($expected);
})->with([
    'indian user, indian contact' => ['9876543210', '+919812345678', '+919876543210'],
    'us user, ambiguous 10 digits' => ['9876543210', '+12025550123', '+19876543210'],
    'us user, us contact' => ['2025550123', '12025550123', '+12025550123'],
    'indian trunk prefix' => ['09876543210', '+12025550123', '+919876543210'],
]);

it('infers the country when the 10 digits only fit one country', function (string $input, string $expected) {
    // No registered phone: 2-5 can only be a US number.
    expect(PhoneNumberNormalizer::normalize($input))->toBe($expected);
})->with([
    'us area code starting with 2' => ['2025550123', '+12025550123'],
    'us area code starting with 4' => ['(415) 555-0123', '+14155550123'],
]);

it('rejects numbers it cannot place in India or the US', function (string $input, ?string $userPhone) {
    expect(PhoneNumberNormalizer::normalize($input, $userPhone))->toBeNull();
})->with([
    'ambiguous without a user country' => ['9876543210', null],
    'ambiguous with a foreign user phone' => ['9876543210', '+447911123456'],
    'other country code' => ['+447911123456', '+919812345678'],
    'too short' => ['98765', '+919812345678'],
    'too long' => ['98765432101234', '+919812345678'],
    'invalid indian mobile' => ['+915876543210', null],
    'invalid us area code' => ['+11025550123', null],
    'empty' => ['', '+919812345678'],
    'letters' => ['not-a-number', '+919812345678'],
]);

it('uses a default country only for numbers that fit both countries', function () {
    expect(PhoneNumberNormalizer::normalize('9876543210', null, 'IN'))->toBe('+919876543210')
        ->and(PhoneNumberNormalizer::normalize('9876543210', null, 'us'))->toBe('+19876543210')
        // The user's registered phone wins over the default.
        ->and(PhoneNumberNormalizer::normalize('9876543210', '+12025550123', 'IN'))->toBe('+19876543210')
        // An unsupported default is ignored.
        ->and(PhoneNumberNormalizer::normalize('9876543210', null, 'GB'))->toBeNull();
});

it('reads the country of a stored phone number', function () {
    expect(PhoneNumberNormalizer::countryOf('+919876543210'))->toBe('IN')
        ->and(PhoneNumberNormalizer::countryOf('12025550123'))->toBe('US')
        ->and(PhoneNumberNormalizer::countryOf('+447911123456'))->toBeNull()
        ->and(PhoneNumberNormalizer::countryOf(null))->toBeNull();
});
