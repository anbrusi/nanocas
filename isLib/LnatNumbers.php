<?php
/**
 * @abstract
 * Implements arithmetics on the set of natural numbers and zero. This set is usually denoted N* 
 * 
 * Natural numbers are numbers in base radix. The "digits" have values from 0 to radix - 1. Digits are themselves PHP integers.
 * The digits are stored in a numeric PHP array. Element 0 is the number of digits. The digits are stored from 1 up, least significant first.
 */
namespace isLib;
class LnatNumbers {
    /**
     * Only natural powers of 10 are possible radices.
     */
    private $radix;
    /**
     * This is the base 10 logarithm of the radix
     */
    private $radixLog;
    function __construct(int $radix) {
        $this->radix = $radix;
        $this->radixLog = intval(round(log10($this->radix)));
    }
    public function strToNn(string $str):array {
        // strip leading '0'
        while (strlen($str) > 0 && $str[0] == '0') {
            $str = substr($str, 1);
        }
        $nn = array();
        $length = strlen($str);
        $charpos = $length - 1;
        $index = 0;
        // Allocate the number of digits
        $nn[$index] = 0;
        $index++;
        $filling = 0;
        $chunk = '';
        // Allocate the digits
        while ($charpos >= 0) {
            $ch = $str[$charpos];
            if (ord($ch) < ord(0) || ord($ch) > ord('9')) {
                return array();
            }
            if ($filling < $this->radixLog) {
                $chunk = $ch.$chunk;
                $filling++;
                $charpos--;
            }
            if ($filling == $this->radixLog || $charpos == -1) {
                $nn[$index] = intval($chunk);
                $index++;
                // Update the number of digits
                $nn[0]++;
                $filling = 0;
                $chunk = '';
            }
        }
        return $nn;
    }
    public function nnToStr(array $nn):string {
        $count = count($nn);
        if ($count == 0) {
            return 'Empty';
        };
        $str = '';
        for ($i = $count - 1; $i > 0; $i--) {
            $chunk = strval($nn[$i]);
            // Pad with leading '0'
            while (strlen($chunk) < $this->radixLog) {
                $chunk = '0'.$chunk;
            }
            $str .= $chunk;
        }
        // strip global leading '0'
        while (strlen($str) > 0 && $str[0] == '0') {
            $str = substr($str, 1);
        }
        return $str;
    }
    public function showNn(array $nn):string {
        $count = count($nn);
        if ($count == 0) {
            return 'Empty';
        };
        $str = '#'.strval($nn[0]).'||';
        for ($i = $count - 1; $i > 0; $i--) {
            $str .= strval($nn[$i]);
            if ($i > 1) {
                $str .= '|';
            }
        }
        return $str;
    }
    /**
     * Pads $nn with leading 0 most significant digits to a length of $length digits and returns the result
     */
    private function zeroPad(array $nn, $length):array {
        $padded = $nn;
        while (count($padded) < $length + 1) {
            // Note that the first array element is not part of the number. 
            $padded[] = 0;
        }
        $padded[0] = $length;
        return $padded;
    }
    public function nnAdd(array $u, array $v):array {
        // Note that the first position is just the number of digits
        $lengthU = count($u) - 1;
        $lengthV = count($v) - 1;
        if ($lengthU > $lengthV) {
            $length = $lengthU;
            $v = $this->zeroPad($v, $length);
        } elseif ($lengthU < $lengthV) {
            $length = $lengthV;
            $u = $this->zeroPad($u, $length);
        } else {
            $length = $lengthU;
        }
        assert($u[0] == $v[0], 'nnAdd has addends of different length');
        $sum = array();
        $sum[0] = 0;
        $carry = 0;
        for ($i = 1; $i <= $length; $i++) {
            $machineSum = $u[$i] + $v[$i] + $carry;
            $sum[$i] = \isLib\LmathMachine::mmMod($machineSum, $this->radix);
            if ($machineSum > $this->radix - 1) {
                $carry = 1;
            } else {
                $carry = 0;
            }
        }
        if ($carry == 1) {
            $sum[$length + 1] = 1;
            $sum[0] = $length + 1;
        } else {
            $sum[0] = $length;
        }
        return $sum;
    }
    /**
     * $u and $v are natural numbers as encoded in this class. 
     * nnCmp returns +1, if $u > $v, -1 if $u < $v and 0 if $u = $v
     */
    public function nnCmp(array $u, array $v):int {
        if ($u[0] > $v[0]) {
            return 1; // u has more digits than v
        } elseif ($u[0] < $v[0]) {
            return -1; // u has less digits than v
        } else {
            // Compare two numbers with an equal number of digits, by comparing digits from most significant down
            // The digits are indexed from 1 (least significant) tuo $u[0]=$v[0]
            for ($i = $u[0]; $i > 0; $i--) {
                if ($u[$i] > $v[$i]) {
                    return 1;
                } elseif ($u[$i] < $v[$i]) {
                    return -1;
                }
            }
        }
        // If we reach this point, $u == $v
        return 0;
    }
    public function nnSub(array $u, array $v):array {
        // Note that the first position is just the number of digits
        $lengthU = count($u) - 1;
        $lengthV = count($v) - 1;
        if ($lengthU > $lengthV) {
            $length = $lengthU;
            $v = $this->zeroPad($v, $length);
        } elseif ($lengthU < $lengthV) {
            $length = $lengthV;
            $u = $this->zeroPad($u, $length);
        } else {
            $length = $lengthU;
        }
        assert($u[0] == $v[0], 'minuend and subtrahend in nnSub have not the same length');
        $diff = array();
        $diff[0] = 0;
        $borrow = 0;
        for ($i = 1; $i <= $length; $i++) {
            $machineDiff = $u[$i] - $v[$i] + $borrow;
            $diff[$i] = \isLib\LmathMachine::mmMod($machineDiff, $this->radix);
            if ($machineDiff < 0) {
                $borrow = -1;
            } else {
                $borrow = 0;
            }
        }
        // Eliminate leading 0 digits
        $length = count($diff) - 1; 
        while ($diff[$length] == 0 && $length > 1) {
            array_pop($diff);
            $length--;
        } 
        $diff[0] = $length;
        return $diff;
    }
    public function nnMult(array $u, array $v):array {
        // Initialize the product with $u[0] + $v[0] digits to 0
        $w = array();
        $w[0] = 0; // This is the counter
        for ($i = 1; $i <= $u[0] + $v[0]; $i++) {
            $w[$i] = 0;
        }
        // Multiply each digit of $v with $u
        for ($j = 1; $j <= $v[0]; $j++) {
            if ($v[$j] != 0) {
                $carry = 0;
                for ($i = 1; $i <= $u[0]; $i++) {
                    $machineProduct = $u[$i] * $v[$j] + $carry + $w[$i + $j - 1];
                    $w[$i + $j -1] = \isLib\LmathMachine::mmMod($machineProduct, $this->radix);
                    $carry = \isLib\LmathMachine::mmDiv($machineProduct, $this->radix);
                }
                $w[$u[0] + $j] = $carry;
            }
        }
        // Eliminate leading 0 digits
        $length = count($w) - 1; 
        while ($w[$length] == 0 && $length > 0) {
            array_pop($w);
            $length--;
        } 
        $w[0] = $length;
        return $w;
    }
    /**
     * Divides a natural number $u by a single digit $v, performing a short division.
     * Returns an array with keys 'quotient' and 'remainder', both natural numbers. 'remainder' has one digit
     */
    public function nnShortDivMod(array $u, int $v):array {
        $quotient = array();
        $quotient[0] = $u[0]; // A priori the quotient has as many digits as the dividend. The leading digit can become 0
        $r = 0;
        for ($i = $u[0]; $i >= 1; $i--) {
            $quotient[$i] = \isLib\LmathMachine::mmDiv($u[$i] + $this->radix * $r, $v);
            $r = \isLib\LmathMachine::mmMod($u[$i] + $this->radix * $r, $v);
        }
        // Eliminate a possible leading 0
        if ($quotient[0] > 0 && $quotient[$quotient[0]] == 0) {
            unset($quotient[$quotient[0]]);
            $quotient[0]--;
        }
        return array('quotient' => $quotient, 'remainder' => array(1, $r));
    }
    /**
     * Multiplies a natural integer $u by a $power of $this->radix, by shifting the digits
     */
    public function nnRadixMult(array $u, int $power):array {
        $product = array();
        $product[0] = $u[0] + $power;
        for ($i = 1; $i <= $power; $i++) {
            $product[$i] = 0;
        }
        for ($i = 1; $i <= $u[0]; $i++) {
            $product[$i + $power] = $u[$i];
        }
        return $product;
    }
    /**
     * Returns the least significant digits, up to digit $endPos as a natural number
     */
    private function slice(array $u, int $endPos):array {
        $slice = array();
        $slice[0] = $endPos;
        for ($i = 1; $i <= $endPos; $i++) {
            $slice[$i] = $u[$i];
        }
        return $slice;
    }
    /**
     * Divides $u by $v and returns an array with keys 'quotient' and 'remainder'.
     * 
     * @param array $u 
     * @param array $v 
     * @return array 
     */
    public function nnDivMod(array $u, array $v):array {
        // Normalize in such a way as to guarantee that the most significant digit of $v > mmDiv($this->radix, 2)
        $d = \isLib\LmathMachine::mmDiv($this->radix, $v[$v[0]] + 1); 
        $nnD = array(1, $d); // Transform the machine integer $d into a natural number, in order to be able to use nnMult
        $un = $this->nnMult($u, $nnD); // m + n digits
        $vn = $this->nnMult($v, $nnD); // n digits
        $n = $vn[0]; // The divisor has n digits
        // The strategy is to split the dividend into a sum of 
        // - a single digit times the divisor times a power of the radix
        // and
        // - a left over summand, having one digit less than the dividend
        // Ex: radix=10, 481:47 -> 481=4x10^2 + 8x10^1 + 1x10^0. This is split into 470 + 11 = 1x47x10^1 + 11. 11 has a digit less than 481
        // The procedure is applied recursively to the left over summand until the left over summand is smaller than the divisor.
        // At each step a partial dividend with n+1 digits is divided by the divisor, yelding a one digit quotient and a remainder

        $quotient = array(1, 0); // Initialize the quotient to one digit, which is 0.
        $done = false;
        while (!$done) {
            $m = $un[0] - $vn[0]; // The dividend has $m digits more than the divisor. $m can be 0 or negative
            if ($m >= 0) {
                if ($m == 0) {
                    // The dividend and the divisor have the same number of digits
                    if ($this->nnCmp($un, $vn) >= 0) {
                        // The dividend is greater or equal to the divisor
                        $partDividend = array();
                        $partDividend[0] = $n + 1; // The partial dividend has $n+1 digits, the most significant beeing a padded leading 0
                        // Take the first $n digits
                        for ($i = 1; $i <= $n; $i++) {
                            $partDividend[$i] = $un[$i];
                        }
                        // Add the most significant digit
                        $partDividend[$n + 1] = 0;
                        $m = 1;
                    } else {
                        // We are done
                        $remainder = $un;
                        $done = true;
                    }
                } else {
                    // Take the first $n+1 digits of the dividend
                    $partDividend = array();
                    $partDividend[0] = $n + 1; // The partial dividend has $n+1 digits, the most significant beeing a padded leading 0
                    for ($i = 1; $i <= $n + 1; $i++) {
                        $partDividend[$i] = $un[$m + $i - 1];
                    }
                }
                if (!$done) {
                    // Compute a trial quotient which is the minimum of 
                    // -- the quotient of the first two digits of the partial dividend and the first digit of the divisor
                    // and
                    // the radix -1
                    $num = $this->radix * $partDividend[$partDividend[0]] + $partDividend[$partDividend[0] - 1];
                    $den = $vn[$vn[0]];
                    $trialQuotient = \isLib\LmathMachine::mmdiv($num, $den);
                    if ($trialQuotient > $this->radix - 1) {
                        $trialQuotient = $this->radix - 1;
                    }
                    // Decrease the trial quotient until it fits
                    $backproduct = $this->nnMult($vn, array(1, $trialQuotient));
                    while ($this->nnCmp($backproduct, $partDividend) == 1) {
                        $trialQuotient--;
                        $backproduct = $this->nnMult($vn, array(1, $trialQuotient));
                    }
                    $leftOver = $this->nnSub($partDividend, $backproduct);
                    // $un = dig(m+n)*radix^(m+n-1) + dig(m+n-1)*radix^(m+n-2) + ... + dig(1)*radix^0 since position 0 of the array is reserved
                    // $un = $partDividend*radix^(m-1) + dig(m-1)*radix^(m-2) + ... + dig(1)*radix^0
                    // Now $partDividend = $trialQuotient * $vn + $leftOver
                    // So $un = $trialQuotient*$vn*radix^(m-1) + $leftOver*radix^(m-1) + dig(m-1)*radix^(m-2) + ... +dig(1)*radix^0
                    // Split the dividend $un into a handled part and an unhandled part, having one digit less
                    // The handled part is $trialQuotient * $vn * $this->radix^($m-1), the unhandled $leftOver*radix^(m-1) + dig(m-1)*radix^(m-2) + ... +dig(1)*radix^0
                    // Multiplication by a power of radix is just a shift of digits
                    $quotient[$m] = $trialQuotient;
                    if ($m > 1) {
                        $leftOver = $this->nnRadixMult($leftOver, $m - 1);
                        $slice = $this->slice($un, $m - 1);
                        $un = $this->nnAdd($leftOver, $slice);
                    } else {
                        $remainder = $leftOver;
                        $done = true;
                    }
                }
            } else {
                // The dividend has less digits than the divisor. We are done. The quotient is 0 and the whole dividend is the remainder
                $remainder = $un;
                $done = true; // but this is redundan, since $done is not queried any more
            }
        }
        $quotient[0] = count($quotient) - 1;
        // Unnormalize the remainder
        $rem = $this->nnShortDivMod($remainder, $d);
        // This is not a typo. The remainder is unnormalized by dividing by the single digit $d
        return array('quotient' => $quotient, 'remainder' => $rem['quotient']);
    }
    /**
     * Returns true iff $u is Zero
     */
    private function nnIsZero(array $u):bool {
        return $u[0] == 0;
    }
    /**
     * Implements Euclid's algorithm. The extended version requires Z and is implemented in LintNumbers
     * 
     * @param array $u 
     * @param array $v 
     * @return array 
     */
    public function nnGCD(array $u, array $v):array {
        while (!$this->nnIsZero($v)) {
            $divmod = $this->nnDivMod($u, $v);
            $r = $divmod['remainder'];
            $u = $v;
            $v = $r;
        }
        return $u;
    }

    /**
     * An array with a 0 in position 0. This is to conform to our representation of natural numbers
     * 
     * @return array 
     */
    public function nnZero():array {
        return array(0);
    }

    public function nnOne():array {
        return array(1,1);
    }
}