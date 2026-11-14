<?php

namespace App\Support\Phone;

/**
 * Turns a phone number typed by a user into E.164 (+919876543210,
 * +12025550123). Only India (+91) and the US (+1) are supported.
 *
 * A number typed without a country code takes the country of the user's own
 * registered phone. A bare 10-digit number starting with 6-9 is valid in
 * both countries: normalize() then needs a default country, and forSms()
 * uses services.sms.default_country.
 */
class PhoneNumberNormalizer
{
    public const INDIA = 'IN';

    public const US = 'US';

    private const CALLING_CODES = [
        self::INDIA => '91',
        self::US => '1',
    ];

    /**
     * @return string|null The E.164 number, or null when it is not a valid
     *                     Indian or US number.
     */
    public static function normalize(string $input, ?string $userPhone = null, ?string $defaultCountry = null): ?string
    {
        $input = trim($input);
        $hasPlus = str_starts_with($input, '+');
        $digits = preg_replace('/\D+/', '', $input) ?? '';

        if ($digits === '') {
            return null;
        }

        // Already in international form: +91XXXXXXXXXX or +1XXXXXXXXXX.
        // A 12-digit 91… or 11-digit 1… number typed without "+" is the
        // same number with the plus left off.
        if ($e164 = self::fromInternational($digits)) {
            return $e164;
        }

        if ($hasPlus) {
            return null;
        }

        // Indian number written with the trunk prefix: 0XXXXXXXXXX.
        if (strlen($digits) === 11 && $digits[0] === '0') {
            return self::build(self::INDIA, substr($digits, 1));
        }

        if (strlen($digits) !== 10) {
            return null;
        }

        $validIn = self::isValidNational(self::INDIA, $digits);
        $validUs = self::isValidNational(self::US, $digits);

        if ($validIn && $validUs) {
            $country = self::countryOf($userPhone) ?? self::supportedCountry($defaultCountry);

            return $country ? self::build($country, $digits) : null;
        }

        if ($validIn) {
            return self::build(self::INDIA, $digits);
        }

        return $validUs ? self::build(self::US, $digits) : null;
    }

    /**
     * The number to text, in E.164. On top of normalize(): a number that
     * fits both countries falls back to services.sms.default_country, and a
     * complete international number from another country ("+44…") is sent
     * as typed. Null only when the input is not a usable phone number.
     */
    public static function forSms(string $input, ?string $userPhone = null): ?string
    {
        $normalized = self::normalize($input, $userPhone, config('services.sms.default_country'));
        if ($normalized) {
            return $normalized;
        }

        $digits = preg_replace('/\D+/', '', $input) ?? '';
        $isInternational = str_starts_with(trim($input), '+')
            && preg_match('/^[1-9]\d{7,14}$/', $digits);

        return $isInternational ? '+'.$digits : null;
    }

    /** The country (IN/US) of an already-stored phone number, if known. */
    public static function countryOf(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if (strlen($digits) === 12 && str_starts_with($digits, '91')
            && self::isValidNational(self::INDIA, substr($digits, 2))) {
            return self::INDIA;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '1')
            && self::isValidNational(self::US, substr($digits, 1))) {
            return self::US;
        }

        return null;
    }

    private static function supportedCountry(?string $country): ?string
    {
        $country = strtoupper(trim((string) $country));

        return isset(self::CALLING_CODES[$country]) ? $country : null;
    }

    private static function fromInternational(string $digits): ?string
    {
        $country = self::countryOf($digits);
        if (! $country) {
            return null;
        }

        return '+'.$digits;
    }

    private static function build(string $country, string $national): ?string
    {
        return self::isValidNational($country, $national)
            ? '+'.self::CALLING_CODES[$country].$national
            : null;
    }

    private static function isValidNational(string $country, string $national): bool
    {
        return match ($country) {
            // Indian mobile numbers: 10 digits starting with 6-9.
            self::INDIA => (bool) preg_match('/^[6-9]\d{9}$/', $national),
            // NANP: area code and exchange both start with 2-9.
            self::US => (bool) preg_match('/^[2-9]\d{2}[2-9]\d{6}$/', $national),
            default => false,
        };
    }
}
