<?php
namespace isLib;
class Lstorage {
    private $fileDir;
    function __construct() {
        $this->filedir = 'storage/';
    }
    /**
     * Stores the array $value under the name $name
     */
    public function store(string $name, array $value) {
        $json = \json_encode($value);
        \file_put_contents($this->filedir.$name.'.txt', $json);
    }
    /**
     * Recalls an array stored under the name $name.
     * The array has keys 'type' and 'value'
     * If it is not found, an empty array is returned
     */
    public function recall(string $name):array {
        $json = \file_get_contents($this->filedir.$name.'.txt');
        if ($json === false) {
            return array();
        }
        return \json_decode($json, true);
    }
}