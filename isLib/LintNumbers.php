<?php
/**
 * @abstract
 * Implements arithmetics on the set of integer numbers. This set is usually denoted Z 
 * The implementation is an extension of \isLib\LnatNumbers taking care of signs
 * 
 * Integer numbers are numbers in base radix. The "digits" have values from 0 to radix - 1. Digits are themselves non negative PHP integers.
 * The digits are stored in a numeric PHP array. Element 0 is positive for positive numbers and negative for negative numbers.
 * Its absolute value is the number of digits. If it is 0, it denotes the number zero and there are no digits.
 * The digits are stored from 1 up, least significant first.
 */
namespace isLib;
class LintNumbers {
    /**
     * An instance of LnatNumbers
     * 
     * @var \isLib\LnatNumbers
     */
    private $LnatNumbers;

    function __construct(int $radix) {
        $this->LnatNumbers = new \isLib\LnatNumbers($radix);
    }
    /**
     * Returns a living instance of LnatNumbers
     * 
     * @return LnatNumbers 
     */
    public function natNumbers():\isLib\LnatNumbers {
        return $this->LnatNumbers;
    }
    /**
     * Changes the sign of an integer number
     * 
     * @param array $u 
     * @return void 
     */
    public function inChgSign(array &$u) {
        if ($u[0] != 0) {
            $u[0] = -$u[0];
        }
    }
    public function strToIn(string $str):array {
        $str = trim($str);
        // Look for a leading minus sign
        if (strpos($str, '-') === 0) {
            $negative = true;
            // Eliminate the leading minus
            $str = substr($str, 1);
        } else {
            $negative = false;
        }
        $u = $this->LnatNumbers->strToNn($str);
        if ($negative) {
            $this->inChgSign($u);
        }
        return $u;
    }
    public function inToStr(array $in):string {
        if ($in[0] < 0) {
            $this->inChgSign($in);
            $negative = true;
        } else {
            $negative = false;
        }
        $str = $this->LnatNumbers->nnToStr($in);
        if ($negative) {
            $str = '-'.$str;
        }
        return $str;
    }
    public function showIn(array $in):string {
        // Display function for natural numbers works as well for integer numbers
        return $this->LnatNumbers->showNn($in);
    }
    /**
     * Algebraic sum of $u and $v
     * 
     * @param array $u 
     * @param array $v 
     * @return array 
     */
    public function inAdd(array $u, array $v):array {
        if ($u[0] < 0) {
            $unegative = true;
            $this->inChgSign($u);
        } else {
            $unegative = false;
        }
        if ($v[0] < 0) {
            $vnegative = true;
            $this->inChgSign($v);
        } else {
            $vnegative = false;
        }
        if (($unegative && $vnegative) || (!$unegative && !$vnegative)) {
            // same sign
            $sum = $this->LnatNumbers->nnAdd($u, $v);
            if ($unegative) {
                // Both summands are negative, change the sum
                $this->inChgSign($sum);
            }
        } else {
            // sign is different
            $comparison = $this->LnatNumbers->nnCmp($u, $v);
            if ($comparison == 1) {
                // $u > $v
                $sum = $this->LnatNumbers->nnSub($u, $v);
                // The sign of $u wins
                if ($unegative) {
                    $this->inChgSign($sum);
                }
            } elseif ($comparison == 0) {
                // $u and $v have different sign, but same absolute value
                $sum = $this->strToIn('0');
            } else {
                // $u < $v
                $sum = $this->LnatNumbers->nnSub($v, $u); 
                // The sign of $v wins
                if ($vnegative) {
                    $this->inChgSign($sum);;
                }
            }
        }
        return $sum;
    }
    /**
     * Returns $u - $v
     * 
     * @param array $u 
     * @param array $v 
     * @return array 
     */
    public function inSub(array $u, array $v):array {
        // Change the sign of $v and add
        $this->inChgSign($v);
        return $this->inAdd($u, $v);
    }
    /**
     * Returns the absolute value of $u
     * 
     * @param array $u 
     * @return array 
     */
    public function inAbs(array $u):array {
        if ($u[0] < 0) {
            $u[0] = -$u[0];
        }
        return $u;
    }
    /**
     * $u and $v are natural numbers as encoded in this class. 
     * nnCmp returns +1, if $u > $v, -1 if $u < $v and 0 if $u = $v
     */
    public function inCmp(array $u, array $v):int {
        if ($u[0] < 0) {
            $unegative = true;
            $this->inChgSign($u);
        } else {
            $unegative = false;
        }
        if ($v[0] < 0) {
            $vnegative = true;
            $this->inChgSign($v);
        } else {
            $vnegative = false;
        }
        if ($unegative && $vnegative) {
            return -$this->LnatNumbers->nnCmp($u,$v);
        } elseif (!$unegative && !$vnegative) {
            return $this->LnatNumbers->nnCmp($u,$v);
        } else {
            // $u and $v have different sign. Note that they cannot be equal, since zero is always counted as positive
            if ($unegative) {
                return -1;
            } else {
                return 1;
            }
        }
    }
    /**
     * Returns the algebraic product of $u and $v
     * 
     * @param array $u 
     * @param array $v 
     * @return array 
     */
    public function inMult(array $u, array $v):array {
        if ($u[0] < 0) {
            $unegative = true;
            $this->inChgSign($u);
        } else {
            $unegative = false;
        }
        if ($v[0] < 0) {
            $vnegative = true;
            $this->inChgSign($v);
        } else {
            $vnegative = false;
        }
        if (($unegative && $vnegative) || (!$unegative && !$vnegative)) {
            // Equal sign, product positive
            return $this->LnatNumbers->nnMult($u,$v);
        } else {
            // Different sign, product negative
            $product =  $this->LnatNumbers->nnMult($u,$v);
            if ($product[0] != 0) {
                // The product could be zero. e.g. -2 * 0
                $this->inChgSign($product);
            }
            return $product;
        }
    }
    /**
     * Divides an integer $u by an integer $v
     * The quotient is positive if $u and $v have the same sign, negative if they have different sign.
     * The absolute value of the remainder is less than the absolute value of the divisor.
     *  7: 3= 2 r= 1
     * -7: 3=-2 r=-1
     *  7:-3=-2 r= 1
     * -7:-3= 2 r=-1
     * 
     * Note that this is not the mathematical convention, where the sign of the remainder is equal to the sign of the divisor
     *  7: 3= 2 r= 1
     * -7: 3=-3 r= 2
     *  7:-3=-3 r=-2
     * -7:-3= 2 r=-1
     * 
     * @param array $u 
     * @param array $v 
     * @return array 
     */
    public function inDivMod(array $u, array $v):array {
        if ($u[0] < 0) {
            $unegative = true;
            $this->inChgSign($u);
        } else {
            $unegative = false;
        }
        if ($v[0] < 0) {
            $vnegative = true;
            $this->inChgSign($v);
        } else {
            $vnegative = false;
        }
        $divMod = $this->LnatNumbers->nnDivMod($u,$v);
        if ($unegative) {
            if ($vnegative) {
                // -7:-3=2 r=-1
                // Change sign of remainder
                $this->inChgSign($divMod['remainder']);
            } else {
                // -7:3=-2 r=-1
                // Change sign of both quotient and remainder
                $this->inChgSign($divMod['quotient']);
                $this->inChgSign($divMod['remainder']);
            }
        } else {
            if ($vnegative) {
                // 7:-3=-2 r=1
                // change sign of quotient
                $this->inChgSign($divMod['quotient']);
            } else {
                // 7:3=2 r=1
                // Nothing to change
            }
        }
        return $divMod;
    }
}