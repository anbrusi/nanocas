<?php
namespace isLib;
class Lencryption {
    private $length;
    private $key;
    private $LnatNumbers;
    function __construct(int $length, int $key) {
        $this->length = $length;
        $this->key = $key;
        $this->LnatNumbers = new \isLib\LnatNumbers(1000);
    }
    public function encrypt(string $txt):array {
        // Normalize $txt
        if (strlen($txt) > $this->length) {
            $txt = substr($txt, 0, $this->length);
        }
        while (strlen($txt) < $this->length) {
            $txt .= ' ';
        }
        $nn = array();
        $nn[0] = $this->length;
        for ($i = 1; $i <= $this->length; $i++) {
            $nn[$i] = ord($txt[$this->length - $i]);
        }
        $enc = $this->LnatNumbers->nnShortDivMod($nn, $this->key);
        $shiftedQuotient = $this->LnatNumbers->nnRadixMult($enc['quotient'], 1);
        return $this->LnatNumbers->nnAdd($shiftedQuotient, $enc['remainder']);
        return $nn;
    }
    public function decrypt(array $nn):string {
        $remainder = array(1, $nn[1]);
        $backShifted = array();
        $backShifted[0] = $nn[0] - 1;
        for ($i = 1; $i <= $backShifted[0]; $i++) {
            $backShifted[$i] = $nn[$i + 1];
        }
        $key = array(1, $this->key);
        $dec = $this->LnatNumbers->nnMult($backShifted, $key);
        $dec = $this->LnatNumbers->nnAdd($dec, $remainder);
        $txt = '';
        for ($i = $dec[0]; $i >= 1; $i--) {
            $txt .= chr($dec[$i]);
        }
        return trim($txt); // Cuts away trailing blanks inserted by the normalisation of input strings
    }
}