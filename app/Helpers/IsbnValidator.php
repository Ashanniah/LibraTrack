<?php

namespace App\Helpers;

class IsbnValidator
{
    /**
     * Validate and clean ISBN
     * 
     * @param string $isbn Raw ISBN input
     * @return array ['valid' => bool, 'error' => string|null, 'cleaned' => string|null]
     */
    public static function validate($isbn)
    {
        // 1. Required Field
        if (empty($isbn)) {
            return ['valid' => false, 'error' => 'ISBN is required.', 'cleaned' => null];
        }

        // Auto-trim whitespace
        $isbn = trim($isbn);
        
        if (empty($isbn)) {
            return ['valid' => false, 'error' => 'ISBN is required.', 'cleaned' => null];
        }

        // 2. Allowed Characters (only digits, hyphens, and spaces - no X allowed for ISBN-13)
        if (!preg_match('/^[0-9\s\-]+$/', $isbn)) {
            return ['valid' => false, 'error' => 'ISBN must contain only digits.', 'cleaned' => null];
        }

        // 3. Clean Format: Remove hyphens and spaces
        // Note: Hyphens and spaces are NOT counted - only digits matter for ISBN-13
        $cleaned = preg_replace('/[\s\-]/', '', $isbn);

        // 4. Check length (must be exactly 13 digits)
        $length = strlen($cleaned);
        
        if ($length !== 13) {
            return ['valid' => false, 'error' => "ISBN must contain exactly 13 digits (you have {$length} digit" . ($length !== 1 ? 's' : '') . "). Hyphens are not counted.", 'cleaned' => null];
        }
        
        // 5. Validate ISBN-13 only
        return self::validateIsbn13($cleaned);
    }


    /**
     * Validate ISBN-13
     * 
     * @param string $isbn Cleaned ISBN (13 characters)
     * @return array
     */
    private static function validateIsbn13($isbn)
    {
        // Character rules: ISBN-13 must be digits only (no X allowed)
        if (!preg_match('/^[0-9]{13}$/', $isbn)) {
            return ['valid' => false, 'error' => 'Invalid character in ISBN-13.', 'cleaned' => $isbn];
        }

        // Checksum validation for ISBN-13
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = (int)$isbn[$i];
            $multiplier = ($i % 2 === 0) ? 1 : 3;
            $sum += $digit * $multiplier;
        }
        
        $checkDigit = (10 - ($sum % 10)) % 10;
        $actualCheckDigit = (int)$isbn[12];
        
        if ($checkDigit !== $actualCheckDigit) {
            return ['valid' => false, 'error' => 'Please enter a valid ISBN-13.', 'cleaned' => $isbn];
        }

        return ['valid' => true, 'error' => null, 'cleaned' => $isbn];
    }

    /**
     * Normalize ISBN for storage (remove hyphens/spaces)
     * 
     * @param string $isbn Raw ISBN
     * @return string Normalized ISBN
     */
    public static function normalize($isbn)
    {
        $isbn = trim($isbn);
        $cleaned = preg_replace('/[\s\-]/', '', $isbn);
        return $cleaned;
    }
}


