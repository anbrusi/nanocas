<?php
class Dispatcher {
    const NEWLINE = "\r\n";

    // Classes instantiated by init
    private $mathMachine;
    private $storage;
    private $nanoCAS;
    private $encryption;

    private $history;
    private function header():string {
        $html = '';
        $html .= '<head>';
        $html .= '<meta charset="utf-8">';
        $html .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        // Load CSS
        $html .= '<link rel="stylesheet" type="text/css" href="index.css" media="screen" />';
        $html .= '</head>';
        return $html;
    }
    private function output(string $out):string {
        $html = '';
        $html .= '<fieldset>';
        $html .= '<legend>output</legend>';
        $html .= '<textarea class="clOutput">';
        $html .= $out;
        $html .= '</textarea>';
        $html .= '</fieldset>';
        return $html;
    }
    private function input(string $input):string {
        $html = '';
        $html .= '<fieldset>';
        $html .= '<legend>input</legend>';
        $html .= '<textarea name="input" autofocus="true" class="clInput">';
        $html .= $input;
        $html .= '</textarea>';
        $html .= '</fieldset>';
        return $html;
    }
    /**
     * The syntax of $command is
     * 
     * command          --> serviceCommand | nanoCASresult | assignement
     * serviceCommand   --> "clr" | "mmdiv" "(" int "," int ")" | "mmmod" "(" int "," int ")" | "tostr" "(" stored-nanoCASobj ")" 
     *                      | "show" "(" stored-nanoCASobj ")" | "nncmp"  "(" parameter "," parameter ")" | "incmp"  "(" parameter "," parameter ")"
     *                      | "decrypt" "(" parameter ")"
     * nanoCASresult    --> "strtonn" "(" parameter ")" | "nnadd" "(" parameter "," parameter ")" | "nnsub" "(" parameter "," parameter ")"
     *                      | "nnmult" "(" parameter "," parameter ")" | "nndiv"  "(" parameter "," parameter ")"
     *                      | "nnmod"  "(" parameter "," parameter ")" | "encrypt" "(" parameter ")"
     *                      | "nngcd" "(" parameter "," parameter ")"
     * 
     *                      | "strtoin" "(" parameter ")" | "inadd" "(" parameter "," parameter ")" | "insub" "(" parameter "," parameter ")"
     *                      | "inabs" "(" parameter ")" | "inmult" "(" parameter "," parameter ")" |
     *                      | "indiv" "(" parameter "," parameter ")"  | "inmod" "(" parameter "," parameter ")"
     * 
     *                      | "strtorn" "(" parameter ")" | "rnadd" "(" parameter "," parameter ")" | "rnsub" "(" parameter "," parameter ")"
     *                      | "rnmult" "(" parameter "," parameter ")"  | "rndiv" "(" parameter "," parameter ")" | "rnpower" "(" parameter ")"
     * parameter        --> varname | nanoCASlit 
     * varname          --> "$" string
     * nanoCASlit       --> string
     * assignement      --> string ":=" nanoCASresult
     * 
     * 
     */
    private function parseCommand(string $command):string {
        $command = trim($command);
        // Check if it is assignament
        $pos = strpos($command, ':=');
        if ($pos !== false) {
            return $this->assignement($command, $pos);
        } else {
            $head = $this->getHead($command);
            if (in_array($head, array('clr', 'mmdiv', 'mmmod', 'tostr', 'show', 'nncmp', 'decrypt', 'incmp'))) {
                return $this->serviceCommand($command);
            } elseif (in_array($head, array('strtonn', 'nnadd', 'nnsub', 'nnmult', 'nndiv', 'nnmod', 'encrypt', 'nngcd',
                                            'strtoin', 'inadd', 'insub', 'inabs', 'inmult', 'indiv', 'inmod',
                                            'strtorn', 'rnadd', 'rnsub', 'rnmult', 'rndiv', 'rnpower'))) {
                return $this->nanoCASresult($command);
            } else {
                throw new Exception('parseCommand: Unrecognized head of command: "'.$head.'"');
            }
        }
    }
    /**
     * production assignaement
     */
    private function assignement(string $command, int $pos):string {
        $varname = substr($command, 0, $pos);
        if ($pos == 0 || $varname[0] != '$') {
            return 'assignement: illegal variable name '.$varname;
        }
        $assigned = substr($command, $pos + 2);
        $obj = $this->nanoCASresultCore($assigned);
        $this->storage->store($varname, $obj);
        return 'stored '.$varname;
    }
    /**
     * Retrieves the head part of $command, up to the first '(' or end of string
     */
    private function getHead(string $command):string {
        $pos = strpos($command, '(');
        if ($pos !== false) {
            return substr($command, 0,$pos);
        }
        return $command;
    }
    /**
     * Production serviceCommand
     */
    private function serviceCommand(string $command):string {
        $sCommand = $this->getHead($command);
        $parameters = $this->getParameters($command); // array of strings
        switch ($sCommand) {
            case 'clr':
                $this->history = '';
                return '';
            case 'mmdiv':
                if (count($parameters) != 2) {
                    throw new Exception('serviceCommand: mmdiv expects 2 parameters');
                }
                if (!is_numeric($parameters[0]) || !is_numeric($parameters[1])) {
                    throw new Exception('serviceCommand: parameters are not numeric');
                }
                $dividend = intval($parameters[0]);
                $divisor = intval($parameters[1]);
                if ($divisor == 0) {
                    throw new Exception('serviceCommand: divisor cannot be 0');
                }
                return strval($this->mathMachine->mmDiv($dividend, $divisor));                
            case 'mmmod':
                if (count($parameters) != 2) {
                    throw new Exception('serviceCommand: mmmod expects 2 parameters');
                }
                if (!is_numeric($parameters[0]) || !is_numeric($parameters[1])) {
                    throw new Exception('serviceCommand: parameters are not numeric');
                }
                $dividend = intval($parameters[0]);
                $divisor = intval($parameters[1]);
                if ($divisor == 0) {
                    throw new Exception('serviceCommand: divisor cannot be 0');
                }
                return strval($this->mathMachine->mmMod($dividend, $divisor));
            case 'show':
                if (count($parameters) != 1) {
                    throw new Exception('serviceCommand: show expects 1 parameter');
                }
                $nanoCASobj = $this->storage->recall($parameters[0]);
                return $this->nanoCAS->show($nanoCASobj);
            case 'tostr':
                if (count($parameters) != 1) {
                    throw new Exception('serviceCommand: show expects 1 parameter');
                }
                $nanoCASobj = $this->storage->recall($parameters[0]);
                return $this->nanoCAS->objToStr($nanoCASobj);
            case 'nncmp':
                if (count($parameters) != 2) {
                    throw new Exception('serviceCommand: nncmp expects 2 parameters');
                }
                $u = $this->parameter($parameters[0], \isLib\LnanoCAS::NCT_NATNUMBERS);
                $v = $this->parameter($parameters[1], \isLib\LnanoCAS::NCT_NATNUMBERS);
                $cmp = $this->nanoCAS->natNumbers->nnCmp($u['value'], $v['value']);
                return strval($cmp); // one of 1, -1, 0
            case 'decrypt':
                if (count($parameters) != 1) {
                    throw new Exception('serviceCommand: show expects 1 parameter');
                }
                $nn = $this->parameter($parameters[0], \isLib\LnanoCAS::NCT_NATNUMBERS);
                return $this->encryption->decrypt($nn['value']);
            case 'incmp':
                if (count($parameters) != 2) {
                    throw new Exception('serviceCommand: incmp expects 2 parameters');
                }
                $u = $this->parameter($parameters[0], \isLib\LnanoCAS::NCT_INTNUMBERS);
                $v = $this->parameter($parameters[1], \isLib\LnanoCAS::NCT_INTNUMBERS);
                $cmp = $this->nanoCAS->intNumbers->inCmp($u['value'], $v['value']);
                return strval($cmp); // one of 1, -1, 0
            default:
                throw new Exception('serviceCommand: unknown command '.$sCommand);
        }
        return 'service command '.$sCommand;
    }
    /**
     * Accepts a command which yields a nanoCASresult and returns the corresponding nanoCAS object
     * with keys 'type' and 'value'
     * Essentially it is the production nanoCASresult, but its result is a nanoCAS object and not a string visualizing this object
     */
    private function nanoCASresultCore(string $command):array {
        $rCommand = $this->getHead($command);
        $parameters = $this->getParameters($command);
        switch ($rCommand) {
            case 'strtonn':
                if (count($parameters) != 1) {
                    throw new Exception('nanoCASresult: strtonn expects 1 parameter');
                }
                return $this->parameter($parameters[0], \isLib\LnanoCAS::NCT_NATNUMBERS);
            case 'nnadd':
                if (count($parameters) != 2) {
                    throw new Exception('nanoCASresult: command "'.$command.'" expects 2 parameters');
                }
                $u = $this->parameter($parameters[0], \isLib\LnanoCAS::NCT_NATNUMBERS);
                $v = $this->parameter($parameters[1], \isLib\LnanoCAS::NCT_NATNUMBERS);
                $nn = $this->nanoCAS->natNumbers->nnAdd($u['value'], $v['value']);
                return array('type' => \isLib\LnanoCAS::NCT_NATNUMBERS, 'value' => $nn);
            case 'nnsub':
                if (count($parameters) != 2) {
                    throw new Exception('nanoCASresult: command "'.$command.'" expects 2 parameters');
                }
                $u = $this->parameter($parameters[0], \isLib\LnanoCAS::NCT_NATNUMBERS);
                $v = $this->parameter($parameters[1], \isLib\LnanoCAS::NCT_NATNUMBERS);
                if ($this->nanoCAS->natNumbers->nnCmp($u['value'], $v['value']) == -1) {
                    throw new Exception('nanoCASresult: minuend cannot be smaller than subtrahend in nnsub');
                }
                $nn = $this->nanoCAS->natNumbers->nnSub($u['value'], $v['value']);
                return array('type' => \isLib\LnanoCAS::NCT_NATNUMBERS, 'value' => $nn);
            case 'nnmult':
                if (count($parameters) != 2) {
                    throw new Exception('nanoCASresult: command "'.$command.'" expects 2 parameters');
                }
                $u = $this->parameter($parameters[0], \isLib\LnanoCAS::NCT_NATNUMBERS);
                $v = $this->parameter($parameters[1], \isLib\LnanoCAS::NCT_NATNUMBERS);
                $nn = $this->nanoCAS->natNumbers->nnMult($u['value'], $v['value']);
                return array('type' => \isLib\LnanoCAS::NCT_NATNUMBERS, 'value' => $nn);
            case 'nndiv':
                if (count($parameters) != 2) {
                    throw new Exception('nanoCASresult: command "'.$command.'" expects 2 parameters');
                }
                $u = $this->parameter($parameters[0], \isLib\LnanoCAS::NCT_NATNUMBERS);
                $v = $this->parameter($parameters[1], \isLib\LnanoCAS::NCT_NATNUMBERS);
                if ($v['value'][0] == 1) {
                    $nn = $this->nanoCAS->natNumbers->nnShortDivMod($u['value'], $v['value'][1]);
                } else {
                    $nn = $this->nanoCAS->natNumbers->nnDivMod($u['value'], $v['value']);
                }
                return array('type' => \isLib\LnanoCAS::NCT_NATNUMBERS, 'value' => $nn['quotient']);
            case 'nnmod':
                if (count($parameters) != 2) {
                    throw new Exception('nanoCASresult: command "'.$command.'" expects 2 parameters');
                }
                $u = $this->parameter($parameters[0], \isLib\LnanoCAS::NCT_NATNUMBERS);
                $v = $this->parameter($parameters[1], \isLib\LnanoCAS::NCT_NATNUMBERS);
                if ($v['value'][0] == 1) {
                    $nn = $this->nanoCAS->natNumbers->nnShortDivMod($u['value'], $v['value'][1]);
                } else {
                    $nn = $this->nanoCAS->natNumbers->nnDivMod($u['value'], $v['value']);
                }
                return array('type' => \isLib\LnanoCAS::NCT_NATNUMBERS, 'value' => $nn['remainder']);
            case 'encrypt':
                if (count($parameters) != 1) {
                    throw new Exception('serviceCommand: encrypt expects 1 parameter');
                }
                $txt = $parameters[0];
                $nn = $this->encryption->encrypt($txt);
                return array('type' => \isLib\LnanoCAS::NCT_NATNUMBERS, 'value' => $nn);
            case 'nngcd':
                if (count($parameters) != 2) {
                    throw new Exception('nanoCASresult: command "'.$command.'" expects 2 parameters');
                }
                $u = $this->parameter($parameters[0], \isLib\LnanoCAS::NCT_NATNUMBERS);
                $v = $this->parameter($parameters[1], \isLib\LnanoCAS::NCT_NATNUMBERS);
                $gcd = $this->nanoCAS->natNumbers->nnGCD($u['value'], $v['value']);
                return array('type' => \isLib\LnanoCAS::NCT_NATNUMBERS, 'value' => $gcd);
            case 'strtoin':
                if (count($parameters) != 1) {
                    throw new Exception('nanoCASresult: strtoin expects 1 parameter');
                }
                return $this->parameter($parameters[0], \isLib\LnanoCAS::NCT_INTNUMBERS);
            case 'inadd':
                if (count($parameters) != 2) {
                    throw new Exception('nanoCASresult: command "'.$command.'" expects 2 parameters');
                }
                $u = $this->parameter($parameters[0], \isLib\LnanoCAS::NCT_INTNUMBERS);
                $v = $this->parameter($parameters[1], \isLib\LnanoCAS::NCT_INTNUMBERS);
                $in = $this->nanoCAS->intNumbers->inAdd($u['value'], $v['value']);
                return array('type' => \isLib\LnanoCAS::NCT_INTNUMBERS, 'value' => $in);
            case 'insub':
                if (count($parameters) != 2) {
                    throw new Exception('nanoCASresult: command "'.$command.'" expects 2 parameters');
                }
                $u = $this->parameter($parameters[0], \isLib\LnanoCAS::NCT_INTNUMBERS);
                $v = $this->parameter($parameters[1], \isLib\LnanoCAS::NCT_INTNUMBERS);
                $in = $this->nanoCAS->intNumbers->inSub($u['value'], $v['value']);
                return array('type' => \isLib\LnanoCAS::NCT_INTNUMBERS, 'value' => $in);
            case 'inabs':
                if (count($parameters) != 1) {
                    throw new Exception('nanoCASresult: command "'.$command.'" expects 1 parameter');
                }
                $u = $this->parameter($parameters[0], \isLib\LnanoCAS::NCT_INTNUMBERS);
                $in = $this->nanoCAS->intNumbers->inAbs($u['value']);
                return array('type' => \isLib\LnanoCAS::NCT_NATNUMBERS, 'value' => $in);
            case 'inmult':
                if (count($parameters) != 2) {
                    throw new Exception('nanoCASresult: command "'.$command.'" expects 2 parameters');
                }
                $u = $this->parameter($parameters[0], \isLib\LnanoCAS::NCT_INTNUMBERS);
                $v = $this->parameter($parameters[1], \isLib\LnanoCAS::NCT_INTNUMBERS);
                $in = $this->nanoCAS->intNumbers->inMult($u['value'], $v['value']);
                return array('type' => \isLib\LnanoCAS::NCT_INTNUMBERS, 'value' => $in);
            case 'indiv':
                if (count($parameters) != 2) {
                    throw new Exception('nanoCASresult: command "'.$command.'" expects 2 parameters');
                }
                $u = $this->parameter($parameters[0], \isLib\LnanoCAS::NCT_INTNUMBERS);
                $v = $this->parameter($parameters[1], \isLib\LnanoCAS::NCT_INTNUMBERS);
                $in = $this->nanoCAS->intNumbers->inDivMod($u['value'], $v['value']);
                return array('type' => \isLib\LnanoCAS::NCT_INTNUMBERS, 'value' => $in['quotient']);
            case 'inmod':
                if (count($parameters) != 2) {
                    throw new Exception('nanoCASresult: command "'.$command.'" expects 2 parameters');
                }
                $u = $this->parameter($parameters[0], \isLib\LnanoCAS::NCT_INTNUMBERS);
                $v = $this->parameter($parameters[1], \isLib\LnanoCAS::NCT_INTNUMBERS);
                $in = $this->nanoCAS->intNumbers->inDivMod($u['value'], $v['value']);
                return array('type' => \isLib\LnanoCAS::NCT_INTNUMBERS, 'value' => $in['remainder']);
            case 'strtorn':
                if (count($parameters) != 1) {
                    throw new Exception('nanoCASresult: strtorn expects 1 parameter');
                }
                return $this->parameter($parameters[0], \isLib\LnanoCAS::NCT_RATNUMBERS);
            case 'rnadd':
                if (count($parameters) != 2) {
                    throw new Exception('nanoCASresult: command "'.$command.'" expects 2 parameters');
                }
                $u = $this->parameter($parameters[0], \isLib\LnanoCAS::NCT_RATNUMBERS);
                $v = $this->parameter($parameters[1], \isLib\LnanoCAS::NCT_RATNUMBERS);
                $in = $this->nanoCAS->ratNumbers->rnAdd($u['value'], $v['value']);
                return array('type' => \isLib\LnanoCAS::NCT_RATNUMBERS, 'value' => $in);
            case 'rnsub':
                if (count($parameters) != 2) {
                    throw new Exception('nanoCASresult: command "'.$command.'" expects 2 parameters');
                }
                $u = $this->parameter($parameters[0], \isLib\LnanoCAS::NCT_RATNUMBERS);
                $v = $this->parameter($parameters[1], \isLib\LnanoCAS::NCT_RATNUMBERS);
                $in = $this->nanoCAS->ratNumbers->rnSub($u['value'], $v['value']);
                return array('type' => \isLib\LnanoCAS::NCT_RATNUMBERS, 'value' => $in);
            case 'rnmult':
                if (count($parameters) != 2) {
                    throw new Exception('nanoCASresult: command "'.$command.'" expects 2 parameters');
                }
                $u = $this->parameter($parameters[0], \isLib\LnanoCAS::NCT_RATNUMBERS);
                $v = $this->parameter($parameters[1], \isLib\LnanoCAS::NCT_RATNUMBERS);
                $in = $this->nanoCAS->ratNumbers->rnMult($u['value'], $v['value']);
                return array('type' => \isLib\LnanoCAS::NCT_RATNUMBERS, 'value' => $in);
            case 'rndiv':
                if (count($parameters) != 2) {
                    throw new Exception('nanoCASresult: command "'.$command.'" expects 2 parameters');
                }
                $u = $this->parameter($parameters[0], \isLib\LnanoCAS::NCT_RATNUMBERS);
                $v = $this->parameter($parameters[1], \isLib\LnanoCAS::NCT_RATNUMBERS);
                $in = $this->nanoCAS->ratNumbers->rnDiv($u['value'], $v['value']);
                return array('type' => \isLib\LnanoCAS::NCT_RATNUMBERS, 'value' => $in);
            case 'rnpower':
                if (count($parameters) != 2) {
                    throw new Exception('nanoCASresult: command "'.$command.'" expects 2 parameters');
                }
                $u = $this->parameter($parameters[0], \isLib\LnanoCAS::NCT_RATNUMBERS);
                $n = $this->parameter($parameters[1], \isLib\LnanoCAS::NCT_STRING);
                $in = $this->nanoCAS->ratNumbers->rnPower($u['value'], intval($n['value']));
                return array('type' => \isLib\LnanoCAS::NCT_RATNUMBERS, 'value' => $in);
            default:
                throw new Exception('nanoCASresultCore: unknown command "'.$command.'"');
        }
    }
    /**
     * Production nanoCASresult
     */
    private function nanoCASresult(string $command):string {
        $nanoCASobj = $this->nanoCASresultCore($command);
        return $this->nanoCAS->show($nanoCASobj);
    }
    /**
     * Returns a nanoCAS object from a variable name or nanoCAS literal of type $type
     */
    private function parameter(string $str, int $type):array {
        if (strlen($str) > 0 && $str[0] == '$') {
            // $str was the name of a stored variable
            return $this->storage->recall($str);
        } else {
            // it was a literal of type $type
            return $this->nanoCAS->strToObj($str, $type);
        }
    }
    /**
     * Filters out the part of $command enclosed in '(' ')' and splits it along ',' returning an array of strings
     * If there are no parentheses an empty array is returned. If there is only an opening or only a closing parentheses, an error is raised
     */
    private function getParameters(string $command):array {        
        $pos = strpos($command,'(');
        $endpos = strpos($command, ')');
        if ($pos === false && $endpos === false) {
            return array();
        }
        if ($pos === false || $endpos === false) {
            throw new Exception('getParameters illegal command '.$command);
        }
        $parameters = substr($command, $pos + 1, $endpos - $pos - 1);
        $parameterArray = explode(',', $parameters);
        foreach ($parameterArray as $key => $value) {
            $parameterArray[$key] = trim($value);
        }
        return $parameterArray;
    }
    private function exMessage(Exception $ex):string {
        return $ex->getMessage();
    }
     /**
     */
    private function body():string {
        $html = '';
        $html .= '<body>';
        $html .= '<h1>nanoCAS</h1>';
        $html .= '<h2><a href="syntax.html" target="_blnk">Syntax</a></h2>';
        $html .= '<form method="POST" method="POST" action = "index.php">';
        if (!isset($_POST['input'])) {
            $_POST['input'] = '';
            $output = '';
        }
        if (!isset($_POST['history'])) {
            $this->history = '';
        } else {
            $this->history = $_POST['history'];
        }
        if (isset($_POST['ok'])) {
            try {
                $result = $_POST['input'].' --> '.$this->parseCommand($_POST['input']);
            } catch (Exception $ex) {
                $result = $this->exMessage($ex);
            }
            if (trim($this->history) != '') {
                $output = $this->history.self::NEWLINE.self::NEWLINE.$result;
            } else {
                $output = $result;
            }
        }
        $html .= $this->output($output);
        $html .= $this->input('');
        $html .= '<input type="submit" name="ok" value="ok" class="isBelow"/>';
        $html .= '<input type="hidden" name="history" value="'.$output.'"/>';
        $html .= '</form>';
        $html .= '</body>';
        return $html;
    }
    public function init() {
        require_once('vendor/autoload.php');
        $this->mathMachine = new \isLib\LmathMachine();
        $this->storage = new \isLib\Lstorage();
        $this->nanoCAS = new \isLib\LnanoCAS(1000);
        $this->encryption = new \isLib\Lencryption(30, 317);
    }
    public function render():string {
        $html = '';
        $html .= '<!DOCTYPE html>'; 
        $html .= '<html lang="en">';
        $html .= $this->header();
        $html .= $this->body();
        $html .= '</html>';
        return $html;
    }
}
$dispatcher = new Dispatcher();
$dispatcher->init();
echo $dispatcher->render();