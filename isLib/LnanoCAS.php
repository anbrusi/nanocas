<?php
/**
 * @abstract
 * All nanoCAS objects have a representation in form of an associative array with keys 'type' and 'value'
 * 'type' is one of the LcasTypes::NCT_xx constants, value is a type dependent array
 */
namespace isLib;
class LnanoCAS {
    const NCT_NATNUMBERS = 1;
    const NCT_INTNUMBERS = 2;
    const NCT_RATNUMBERS = 3;
    const NCT_STRING = 4;

    /**
     * @var \isLib\LnatNumbers instance
     */
    public $natNumbers;
    /**
     * @var \isLib\LintNumbers instance
     */
    public $intNumbers;
    /**
     * 
     * @var \isLib\LratNumbers instance
     */
    public $ratNumbers;
    /**
     * $radix must be 10 raised to a natural Power, e.g. 10, 100, 1000, 10000...
     * since the underlying LmathMachine uses numbers with a decimal radix
     */
    function __construct(int $radix) {
        $this->natNumbers = new \isLib\LnatNumbers($radix);
        $this->intNumbers = new \isLib\LintNumbers($radix);
        $this->ratNumbers = new \isLib\LratNumbers($radix);
    }
    /**
     * Returns a nanoCAS object of type $type from string $str
     */
    public function strToObj(string $str, int $type):array {
        switch ($type) {
            case self::NCT_NATNUMBERS:
                $value = $this->natNumbers->strToNn($str);
                return array('type' => $type, 'value' => $value);
            case self::NCT_INTNUMBERS:
                $value = $this->intNumbers->strToIn($str);
                return array('type' => $type, 'value' => $value);
            case self::NCT_RATNUMBERS:
                $value = $this->ratNumbers->strToRn($str);
                return array('type' => $type, 'value' => $value);
            case self::NCT_STRING:
                return array('type' => $type, 'value' => $str);
            default:
                throw new \Exception('LnanoCAS::strToObj: unknown type '.$type);
        }
    }
    /**
     * Returns a string from a nanoCAS object
     */
    public function objToStr(array $obj):string {
        $type = $obj['type'];
        switch ($type) {
            case self::NCT_NATNUMBERS:
                return $this->natNumbers->nnToStr($obj['value']);
            case self::NCT_INTNUMBERS:
                return $this->intNumbers->inToStr($obj['value']);
            case self::NCT_RATNUMBERS:
                return $this->ratNumbers->rnToStr($obj['value']);
            default:
                throw new \Exception('LnanoCAS::objToStr: unknown type '.$type);
        }
    }
    /**
     * Returns a string describing the nanoCAS object obj
     */
    public function show(array $obj):string {
        $type = $obj['type'];
        switch ($type) {
            case self::NCT_NATNUMBERS:
                return $this->natNumbers->showNn($obj['value']);
            case self::NCT_INTNUMBERS:
                return $this->intNumbers->showIn($obj['value']);
            case self::NCT_RATNUMBERS:
                return $this->ratNumbers->showRn($obj['value']);
            default:
                throw new \Exception('LnanoCAS::objToStr: unknown type '.$type);
        }
    }
}