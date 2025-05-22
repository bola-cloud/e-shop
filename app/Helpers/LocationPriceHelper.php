<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Request;
use Stevebauman\Location\Facades\Location;

class LocationHelper
{
    /**
     * Supported country codes for pricing.
     *
     * @var array
     */
    protected static $supportedCountries = ['ARE', 'KSA', 'UAE'];

    /**
     * Get the country code for the current user.
     *
     * @return string
     */
    public static function getCountryCode(): string
    {
        // 1. Use cached country code if available
        if (Session::has('country_code')) {
            $code = Session::get('country_code');
            if (in_array($code, self::$supportedCountries)) {
                return $code;
            }
        }

        // 2. Check query parameter for testing
        if (Request::has('country_code')) {
            $code = strtoupper(Request::get('country_code'));
            if (in_array($code, self::$supportedCountries)) {
                Session::put('country_code', $code);
                return $code;
            }
        }

        // 3. Use IP to detect location
        $ip = Request::ip();
        $position = Location::get($ip);

        if ($position && !empty($position->countryCode)) {
            $code = strtoupper($position->countryCode);
            if (in_array($code, self::$supportedCountries)) {
                Session::put('country_code', $code);
                return $code;
            }
        }

        // 4. Fallback default
        return 'UAE';
    }
}
