<?php
/**
 * @abstract
 * PHP interface to native handling of integers
 */
namespace isLib;
class LmathMachine {
    public static function mmDiv(int $dividend, int $divisor):int {
        return \intdiv($dividend, $divisor);
    }
    public static function mmMod(int $dividend, int $divisor):int {
        $phpMod = $dividend % $divisor;
        if ($phpMod >= 0) {
            return $phpMod;
        } else {
            return $divisor + $phpMod;
        }
    }
}