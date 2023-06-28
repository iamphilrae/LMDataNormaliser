<?php

namespace App\Helpers;

/**
 * Luhn algorithm (a.k.a. modulus 10) is a simple formula used to validate variety of identification numbers.
 * It is not intended to be a cyrptographically secure hash function, it was designed to protect against accidental errors.
 * See http://en.wikipedia.org/wiki/Luhn_algorithm
 *
 * @author Rolands KusiÅ†Å¡
 * @version 0.2
 * @license GPL
 */

class Luhn
{

    private array $sum_table = [
        [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
        [0, 2, 4, 6, 8, 1, 3, 5, 7, 9]
    ];

    /**
     * Calculate check digit according to Luhn's algorithm
     * New method (suggested by H.Johnson), see http://www.phpclasses.org/discuss/package/8471/thread/1/
     *
     * @param string $number
     * @return string
     */
    public function calculateCheckDigit( string $number ): string
    {
        $length = strlen($number);
        $sum = 0;
        $flip = 1;

        // Sum digits (last one is check digit, which is not in parameter)
        for($i = $length-1; $i >= 0; --$i)
            $sum += $this->sum_table[$flip++ & 0x1][$number[$i]];

        // Multiply by 9
        $sum *= 9;

        // Last digit of sum is check digit
        return substr($sum, -1, 1);
    }

    /**
     * Validate number against check digit, with the check digit being the last digit in the number.
     *
     * @param string $number
     * @return bool
     */
    public function validateCheckDigit( string $number ): bool
    {
        $supplied_number = substr( $number, 0, -1 );
        $supplied_digit = (int)substr( $number, -1, 1 );

        $calculated_digit = $this->calculateCheckDigit( $supplied_number );

        return ($supplied_digit == $calculated_digit);
    }

    /**
     * @param string $number
     * @return string
     */
    public function appendChecksum( string $number ): string {
        return $number . $this->calculateCheckDigit($number);
    }
}
