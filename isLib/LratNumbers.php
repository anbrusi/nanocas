<?php
/**
 * @abstract
 * Implements arithmetics on the set of rational numbers. This set is usually denoted Q 
 * The implementation is an extension of \isLib\LintNumbers
 * 
 * Rational numbers are quotients of integer numbers in base radix. 
 * The "digits" have values from 0 to radix - 1. Digits are themselves non negative PHP integers.
 * The digits are stored in a numeric PHP array. Element 0 is positive for positive numbers and negative for negative numbers.
 * Its absolute value is the number of digits. If it is 0, it denotes the number zero and there are no digits.
 * The digits are stored from 1 up, least significant first.
 * 
 * The rational number is a numeric array with the numerator in position 0 and the denominator in position 1
 * Denominators are always positive, the sign is the sign of the numerator
 * Rational numbers are always stored in the most simplified form. The GDC of numerator and denominator is 0.
 */
namespace isLib;

use Exception;

class LratNumbers {

    /**
     * An instance of \isLib\LnatNumbers
     */
    private $LnatNumbers;
    /**
     * An instance of \isLib\LintNumbers
     */
    private $LintNumbers;

    function __construct(int $radix) {
        $this->LintNumbers = new \isLib\LintNumbers($radix);
        $this->LnatNumbers = $this->LintNumbers->natNumbers();
    }

    /**
     * Reduces a rational number to its simplest form,
     * by dividing numerator and denominator by the greatest common divisor
     * 
     * @param array $u 
     * @return array 
     */
    private function inReduce(array $u):array {
        $absNumerator = $this->LintNumbers->inAbs($u[0]);
        $gcd = $this->LnatNumbers->nnGCD($absNumerator, $u[1]);
        if ($gcd[0] != 1 || $gcd[1] !=1) {
            // GCD was NOT 1, divide
            $numerator = $this->LintNumbers->inDivMod($u[0], $gcd)['quotient'];
            $denominator = $this->LnatNumbers->nnDivMod($u[1], $gcd)['quotient'];
            return array($numerator, $denominator);
        } else {
            // GCD = 1, aready reduced
            return $u;
        }
    }
    /**
     * Returns a rational number from a string of the form numerator/denominator. 
     * The denominator will be positive and the fraction will be in its shortest form
     * 
     * @param string $str 
     * @return array 
     */
    public function strToRn(string $str):array {
        $parts = explode('/', $str);
        if (count($parts) != 2) {
            throw new \Exception('Illegal string format for a rational number: '.$str);
        }
        $numerator = $this->LintNumbers->strToIn(trim($parts[0]));
        $denominator = $this->LintNumbers->strToIn(trim($parts[1]));
        if ($denominator[0] == 0) {
            throw new \Exception('The denominator cannot be zero');
        }
        if ($denominator[0] < 0) {
            $this->LintNumbers->inChgSign($numerator);
            $this->LintNumbers->inChgSign($denominator);
        }
        return $this->inReduce(array($numerator, $denominator));
    }

    /**
     * Returns a string representation of $u like -12/98 
     * 
     * @param array $u 
     * @return string 
     */
    public function rnToStr(array $u):string {
        return $this->LintNumbers->inToStr($u[0]).'/'.$this->LnatNumbers->nnToStr($u[1]);
    }
    /**
     * Returns a rational number as numerator "/" denominator
     * @param array $u 
     * @return string 
     */
    public function showRn(array $u):string {
        return $this->LintNumbers->showIn($u[0]).'/'.$this->LnatNumbers->showNn($u[1]);
    }

    /**
     * Let $u = a/b and $v = c/d. Then the result is r=(ad+cb)/bd.
     * Let g = GCD(b,d). Dividing numerator and denominator of r by g we get r=(a(d/g) + c(b/g)) / ((b/g)d).
     * Since g is a divisor of both b and d, the fractions (d/g) and (b/g) are natural numbers
     * Define s=b/g and t=d/g. Then r=(at+cs) / (sc)
     * 
     * @param array $u 
     * @param array $v 
     * @return array 
     */
    public function rnAdd(array $u, array $v):array {
        $g = $this->LnatNumbers->nnGCD($u[1],$v[1]);
        // Special case, where $g is one
        if ($g[0] == 1 && $g[1] == 1) {
            $ad = $this->LintNumbers->inMult($u[0], $v[1]);
            $cb = $this->LintNumbers->inMult($v[0], $u[1]);
            $numerator = $this->LintNumbers->inAdd($ad, $cb);
            $denominator = $this->LnatNumbers->nnMult($u[1], $v[1]);
        } else {
            $s = $this->LnatNumbers->nnDivMod($u[1], $g)['quotient'];
            $t = $this->LnatNumbers->nnDivMod($v[1], $g)['quotient'];
            $at = $this->LintNumbers->inMult($u[0], $t);
            $cs = $this->LintNumbers->inMult($v[0], $s);
            $numerator = $this->LintNumbers->inAdd($at, $cs);
            $denominator = $this->LnatNumbers->nnMult($s, $v[1]);
        }
        return array($numerator, $denominator);
    }

    /**
     * 
     * @param array $u 
     * @param array $v 
     * @return array 
     */
    public function rnSub(array $u, array $v):array {
        $this->LintNumbers->inChgSign($v[0]); // Change the sign of the numerator of $v
        return $this->rnAdd($u,$v);
    }

    /**
     * Let $u = a/b and $v = c/d The unreduced result is r=ac/bd. 
     * We could compute this and call $this->inReduce, but it is more efficient to note
     * that GCD(ac,bd) = GCD(a,d)GCD(b,c) and reduce in place, because the numbers involved are smaller
     * 
     * @param array $u 
     * @param array $v 
     * @return array 
     */
    public function rnMult(array $u, array $v):array {
        $aAbsolute = $this->LintNumbers->inAbs($u[0]);
        $cAbsolute = $this->LintNumbers->inAbs($v[0]);
        $gad = $this->LnatNumbers->nnGCD($aAbsolute, $v[1]);
        $gbc = $this->LnatNumbers->nnGCD($u[1], $cAbsolute);
        $g = $this->LnatNumbers->nnMult($gad, $gbc);
        $numerator = $this->LintNumbers->inMult($u[0], $v[0]);
        $denominator = $this->LnatNumbers->nnMult($u[1], $v[1]);
        if ($g[0] != 1 || $g[1] != 1) {
            // Reduction is needed
            $numerator = $this->LintNumbers->inDivMod($numerator, $g)['quotient'];
            $denominator = $this->LnatNumbers->nnDivMod($denominator, $g)['quotient'];
        }
        return array($numerator, $denominator);
    }

    /**
     * Flips numerator and denominator, taking care of keeping the denominator positive
     * 
     * @param array $u 
     * @return array 
     */
    private function rnReciprocal(array $u):array {
        $pivot = $u[0];
        $u[0] = $u[1];
        $u[1] = $pivot;
        if ($u[1][0] < 0) {
            $this->LintNumbers->inChgSign($u[0]);
            $this->LintNumbers->inChgSign($u[1]);
        }
        return $u;
    }

    /**
     * Divides $u by $v
     * 
     * @param array $u 
     * @param array $v 
     * @return array 
     */
    public function rnDiv(array $u, array $v):array {
        $v = $this->rnReciprocal($v);
        return $this->rnMult($u, $v);
    }

    /**
     * Returns the rational number 0. In fact this is 0/1 to conform to our representation of rational numbers
     * @return array 
     */
    public function rnZero():array {
        $numerator = $this->LnatNumbers->nnZero();
        $denominator = $this->LnatNumbers->nnOne();
        return array($numerator, $denominator);
    }

    /**
     * Returns the rational number 1. In fact this is 1/1 to conform to our representation of rational numbers
     * 
     * @return array 
     */
    public function rnOne():array {
        $nnOne = $this->LnatNumbers->nnOne(); // This is the natural number 1
        return array($nnOne, $nnOne);
    }

    /**
     * Returns an integer power of a rational number. Negative bases and negative exponents are allowed.
     * The only case throwing an exception is zero raised to a negative exponent
     * 
     * @param array $u 
     * @param int $n 
     * @return array 
     * @throws Exception 
     */
    public function rnPower(array $u, int $n):array {
        if ($n < 0) {
           $n = -$n;
           $negativeExponent = true;
           if ($u[0][0] == 0) {
               // Numerator is zero
               throw new \Exception('No negative power of zero is defined');
           }
        } else {
            $negativeExponent = false;
        }
        if ($u[0][0] < 0) {
            $u[0] = $this->LintNumbers->inAbs($u[0]); // Take the absolute value of the base
            $negativeBase = true;
        } else {
            $negativeBase = false;
        }
        $result = $this->rnOne();
        $currPower = $u;
        while ($n > 0) {
            if ($n & 1) {
                // The least significant bit is set, so multiply by the current power
                $result = $this->rnMult($result, $currPower);
            }
            $n = $n >> 1;
            // The current power is the square of the previous
            $currPower = $this->rnMult($currPower, $currPower);
        }
        if ($negativeExponent) {
            $result = $this->rnReciprocal($result);
        }
        if ($negativeBase) {
            $this->LintNumbers->inChgSign($result[0]); // Change sign of numerator of result
        }
        return $result;
    }
}