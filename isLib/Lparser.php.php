<?php
/**
 * @abstract
 * Small CAS system for isTest
 * 
 * 
 * The lexer accepts the following "mathexp" synatx:
 * 
 * ----------------------------------------------------------------------------------------------------------------------------------
 * EBNF
 * ====
 * 
 * block		-> boolexp
 * boolexp		-> boolterm { "|" boolterm}
 * boolterm		-> boolfactor { "&" boolfactor } 
 * boolfactor	-> boolatom | "[" boolexp "]"
 * boolatom		-> expression [compareop expression]
 * compareop	-> "=" | ">" | ">=" | "<" | "<=" | "<>"
 * expression	-> ["-"] term { addop term}
 * term			-> factor { mulop factor}
 * factor		-> atom ["^" factor] | "(" expression ")"
 * atom			-> mathconst | number | variable | funct
 * mathconst	-> "PI" | "E"
 * number		-> digit {digit} ["." digit {digit}]
 * digit		-> "0" | "1" | ... "9"
 * variable		-> alpha except alternatives in functionname if $this->oneCharVariables == true
 * variable		-> alpha {alpha} except alternatives in functionname if $this->oneCharVariables == false
 * alpha		-> -small ascii letter-
 * funct		-> functionname "(" expression ")"
 * functionname	-> "ABS" | "SQRT" | "EXP" | "LN" | "LOG" | "SIN" | "COS" | "TAN" | "ASIN" | "ACOS" | "ATAN"		
 * addop		-> "+" | "-" 
 * mulop		-> "*" | "/" | "?"
 * 
 * Exponentiation is right associative https://en.wikipedia.org/wiki/Exponentiation. This means a^b^c is a^(b^c) an NOT (a^b)^c
 * Math machine implements this correctly
 * 
 * Parentheses around boolexp must be different from parentheses around expression. If not (a + b) " x would interpret the firs "(" as start of boolexp
 * 
 * number is POSITIVE. There are no negative numbers in "mathexp". Where needed the operator self::N_UNARYMINUS is used.
 * -----------------------------------------------------------------------------------------------------------------------------------
 * 
 * Strings adhering to this syntax are transformed by the lexer into an array of tokens stored in $this->tokens
 * 
 * 
 * @author A. Brunnschweiler
 * @version 11.11.2019
 */
namespace isLib;
class LmicroCAS {
	
	const TAB = '  ';
	const BR = "\r\n";
	
	/**
	 * Error origin. 
	 * Each functional constituent sets the origin of subsequent errors $this->errOrigin by calling $this->setErrOrigin
	 */
	const EO_LEXER = 1;
	const EO_PARSER = 2;
	const EO_LATEX = 3;
	const EO_EVALUATION = 4;
	
	/**
	 * Errors, that can occur in LmicroCAS, which are handled by $this->error	 *
	 */
	const ERR_NOT_ASCII = 1; // The mathexp string submitted to the lexer contains non ASCII characters
	const ERR_NO_INPUT = 2; // The mathexp string submitted to the lexer is empty. The check is made after eliminating blank space
	const ERR_PREMATURE_END = 3; // Premature end of the mathexp string
	const ERR_ILLEGAL_CHAR = 4; // Illegal character found by lexer. The character found cannot be associated to a token.
	const ERR_VAR_SORT = 5; // Error sorting variable names detected by the parser
	const ERR_OR_EXPECTED = 6; // Parser expected '|'
	const ERR_AND_EXPECTED = 7; // Parser expected '&'
	const ERR_BOOL_TERM_EXPECTED = 8;
	const ERR_BOOL_FACTOR_EXPECTED = 9;
	const ERR_EXPRESSION_EXPECTED = 10;
	const ERR_TERM_EXPECTED = 11;
	const ERR_FACTOR_EXPECTED = 12;
	const ERR_LPAREN_EXPECTED = 13;
	const ERR_RPAREN_EXPECTED = 14;
	const ERR_ATOM_EXPECTED = 15;
	const ERR_COMPARE_OPERATOR_EXPECTED = 16;
	const ERR_BOOLEXP_EXPECTED = 17;
	const ERR_UNKNOWN_NODE_TYPE = 18;
	const ERR_UNKNOWN_FUNCTION = 19;
	const ERR_UNKNOWN_MATHCOST = 20;
	const ERR_NO_PARSETREE = 21; // Happens when the evaluator is called on an empty parse tree
	const ERR_MISSING_VAR = 22; // Variable has value, but is missing in the syntax tree
	const ERR_MISSING_VAR_VALUE = 23; // Variable in syntax tree has no value
	const ERR_VAR_NOT_NUMERIC = 24;
	const ERR_ZERO_DENOMINATOR = 25;
	const ERR_EMPTY_SYNTAX_TREE = 26;
	const ERR_EMPTY_MULTINODE_TREE = 27;
	const ERR_TERM_MULTINODE_EXPECTED = 28;
	
	/**
	 * Tokens of the mathexp syntax.
	 * 
	 * These are the symbols that can occur in the mathexp syntax, except for number and variable
	 * Number is not entirely specified by a symbol but needs additionally a value. Variable needs a name
	 */
	const TK_OR = 1;
	const TK_AND = 2;
	const TK_EQ = 3;
	const TK_GT = 4;
	const TK_GTEQ = 5;
	const TK_SM = 6;
	const TK_SMEQ = 7;
	const TK_NOTEQ = 8;
	const TK_MINUS = 9;
	const TK_CARET = 10;
	const TK_LPAREN = 11;
	const TK_RPAREN = 12;
	const TK_NUMBER = 13;
	const TK_VAR = 14;
	const TK_PLUS = 15;
	const TK_MULT = 16;
	const TK_DIV = 17;
	const TK_IMPMULT = 18;
	const TK_LBOOLPAREN = 19;
	const TK_RBOOLPAREN = 20;
	// mathematical constants
	const TK_PI = 101;
	const TK_E = 102;
	// Miscellaneous functions
	const TK_ABS = 201;
	const TK_SQRT = 202;
	// Exponential functions
	const TK_EXP = 211;
	const TK_LOG = 212;
	const TK_LOG10 = 213;
	// Trigonometric functions
	const TK_SIN = 221;
	const TK_COS = 222;
	const TK_TAN = 223;
	const TK_ASIN = 224;
	const TK_ACOS = 225;
	const TK_ATAN = 226;
	
	
	/**
	 * Mathematical constant names in the mathexp syntax, indexed by their token
	 *
	 * @var array
	 */
	const MATHCONST_NAMES = array(
		self::TK_PI => 'PI',
		self::TK_E => 'E'
	);
	
	/**
	 * Function names in the mathexp syntax, indexed by their token
	 * 
	 * @var array
	 */
	const FUNCTION_NAMES = array(
		self::TK_ABS => 'ABS',
		self::TK_SQRT => 'SQRT',
		self::TK_EXP => 'EXP',
		self::TK_LOG => 'LN',
		self::TK_LOG10 => 'LOG',
		self::TK_SIN => 'SIN',
		self::TK_COS => 'COS',
		self::TK_TAN => 'TAN',
		self::TK_ASIN => 'ASIN',
		self::TK_ACOS => 'ACOS',
		self::TK_ATAN => 'ATAN'		
	);
	
	/****************************************************************************************************
	 * Node types ALL nodes have a key 'ntype'= one of the N_xx constants and 'startpos'
	 * 'startpos is the starting position within the string submitted to the lexer or -1 if not specified
	 ****************************************************************************************************/
	/**
	 * Keys: 'value' the number as string,
	 */
	const N_NUMBER = 1;
	/**
	 * Keys: 'value' the name of the variable
	 */
	const N_VARIABLE = 2;
	/**
	 * Keys: 'left', 'right'
	 */
	const N_OROP = 3;
	/**
	 * Keys: 'left', 'right'
	 */
	const N_ANDOP = 4;
	/**
	 * Keys: 'left', 'right'
	 */
	const N_EQOP = 5;
	/**
	 * Keys: 'left', 'right'
	 */
	const N_GTOP = 6;
	/**
	 * Keys: 'left', 'right'
	 */
	const N_GTEQOP = 7;
	/**
	 * Keys: 'left', 'right'
	 */
	const N_SMOP = 8;
	/**
	 * Keys: 'left', 'right'
	 */
	const N_SMEQOP = 9;
	/**
	 * Keys: 'left', 'right'
	 */
	const N_NOTEQOP = 10;
	/**
	 * Keys: child
	 */
	const N_UNARYMINUS = 11;
	/**
	 * Keys: 'left', 'right'
	 */
	const N_ADDOP = 12;
	/**
	 * Keys: 'left', 'right'
	 */
	const N_SUBOP = 13;
	/**
	 * Keys: 'left', 'right'
	 */
	const N_MULOP = 14;
	/**
	 * Keys: 'left', 'right'
	 */
	const N_IMPMULOP = 15; // Implicit multiplication. It is distinguished from multiplication to be able to represent it with no or another symbol	
	/**
	 * Keys: 'left'= numerator, 'right'= denominator
	 */
	const N_DIVOP = 16;
	/**
	 * Keys: 'left'= base, 'right'= exponent
	 */
	const N_EXPOP = 17;
	/**
	 * Keys: 'ctoken' one of the TK_xx tokens for a specific mathematical constant
	 */
	const N_MATHCONST = 18;
	/**
	 * Keys: 'ftoken'= one of the TK_xx tokens for a specific function. This denotes the function type, 'child'=argument
	 */
	const N_FUNCT = 19;
	
	// Multinodes and holders of their children ----------------------------------------------------------------
	/**
	 * Keys: 'children' = numeric array of node holders
	 */
	const NM_EXPRESSION = 101;
	/**
	 * Keys 'child' = a whole tree representing a term, added to the expression
	 */
	const NH_PLUS = 102;
	/**
	 * Keys: 'child = a whole tree representing a term subtracted from the expression
	 */
	const NH_MINUS = 103;
	/**
	 * Keys: 'children' = numeric array of node holders
	 */
	const NM_TERM = 104;
	/**
	 * Keys 'child' = a whole tree representing a factor, added to the term
	 */
	const NH_NUMERATOR = 105;
	/**
	 * Keys: 'child = a whole tree representing a factor, added to the term
	 */
	const NH_DENOMINATOR = 106;
	
	/**
	 * The evaluator will consider numbers with absolute value below this barrier to be zero
	 * 
	 * @var double
	 */
	const EVAL_ZERO_BARRIER = 1.0E-30;
	
	
	// Error related -----------------------------------------------------------------------------------------
	
	/**
	 * Origin of subsequent errors thrown by $this->error
	 * 
	 * @var int One of the self:EO_xx constants
	 */
	private $errOrigin;
	
	/**
	 * The error position within $this->mathexp. It is set by the lexer. If no eror occurred in the lexer, it is null
	 *
	 * @var int
	 */
	private $errpos;
	
	// Lexer related --------------------------------------------------------------------------------------------
	
	/**
	 * Numbers are rounded by the lexer to $this->rounding decimals. Default is -1, which means no rounding
	 *
	 * @var int
	 */
	private $rounding;
	
	/**
	 * Set by the constructor. If true variable names are always one character,
	 * if false arbytrary names, that do not collide with function names can be used.
	 *
	 * @var bool
	 */
	private $oneCharVariables;
	
	/**
	 * The mathematical expression submitted to the lexer
	 * 
	 * @var string
	 */
	private $mathexp;
	
	/**
	 * The lexer transforms the string in mathexp syntax into the array $this->tokens
	 * Each element is a token, which is itself an array with keys 'tk', 'startpos' and possibly 'value'
	 * 'tk' is one of the self::TK_xx constants, 'startpos' the starting position of the token within the mathexp string,
	 * 'value' is defined only for TK_NUMBER and is the string representing the number
	 *
	 * @var array
	 */
	private $tokens;
	
	// Parser related --------------------------------------------------------------------------------------------
	
	/**
	 * The variables detected by the parser in alphabetical order. Numeric array of variable names
	 * 
	 * @var array
	 */
	private $variables;
	/**
	 * The number of tokens in $this->tokens available to the parser. Set on parser initialisation
	 * 
	 * @var int
	 */
	private $nrTokens;
	/**
	 * Pointer into $this->tokens maintained by the parser
	 * 
	 * @var int
	 */
	private $currToken;
	/**
	 * A numeric array of productions, as they are encountered by the parser.
	 * Each production is a string with it's name.
	 * 
	 * @var array
	 */
	private $parseTrace;
	/**
	 * The syntax tree produced by the parser. An empty array if the parser did not produce a tree after the last reset
	 * @var array
	 */
	private $tree;
	/**
	 * This is a syntax tree with multinodes, i.e. nodes having a subnode 'children' which is a numeric array of node holders.
	 * As opposed to the binary syntax tree this tree does not specify the order in which commutative operastions are executed,
	 * but evidences operator precedence.
	 * 
	 * There is a multinode NM_EXPRESSION, whose children are the terms in an expresion. Its holders are NH_PLUS and NH_MINUS,
	 * which specify the sign of their 'child' subtree, thus distinguishing between terms added and terms subtracted from the expression.
	 * 
	 * There is a multinode NM_TERM, whode children are the factors in a term. Its holders are N_NUMERATOR and N_DENOMINATOR,
	 * which specify if their subtree 'child' belongs to the numerator or denominator of the term. Terms in this context are a fractin
	 * 
	 * The function $this->binaryToMultinode() builds this->mtree from $this->tree
	 * 
	 * @var array
	 */
	private $mtree;
	
	// Evaluation related -------------------------------------------------------------------------------------------------
	
	
	/**
	 * If true, evaluator uses radiands (default), if false it uses degrees
	 * 
	 * @var bool
	 */
	private $useRad;
	
	/**
	 * Array of variable values, indexed by variable name
	 * 
	 * @var array
	 */
	private $varValues;
	
	
	// Methods -----------------------------------------------------------------------------------------------
	
	/**
	 * If $oneCharVariable is true variable names can only be a single lowercase letter.
	 * This allows to insert implicit multiplications between letters in the second pass of the parser.
	 * If multiletter Variables are allowed, multiplication between two variables must be explicit.
	 *
	 * @param bool $oneCharVariables
	 */
	function __construct(bool $oneCharVariables) {
		$this->oneCharVariables = $oneCharVariables;
		$this->reset();
	}
	/**
	 * Completely resets LmicroCAS
	 */
	public function reset() {
		// Lexer
		$this->tokens = array(); // Clear the token array
		$this->errpos = null; // No lexer error as far
		$this->rounding = -1; // No rounding by default
		// Parser
		$this->variables = array();
		$this->nrTokens = 0;
		$this->parseTrace = array(); // array of production names set by the parser
		$this->tree = array();
		$this->mtree = array();
		$this->useRad = true; // Trigonometric functions use radians by dfault
		$this->varValues = array();
	}
	
	// Error ----------------------------------------------------------------------------------------------
	
	/**
	 * Sets the origin for subsequent errors to one of the self::EO_xx constants
	 * 
	 * @param int $errOrigin
	 */
	private function setErrOrigin(int $errOrigin) {
		$this->errOrigin = $errOrigin;
	}
	
	/**
	 * Returns the origin of an error thrown by $this->error
	 * 
	 * @return int
	 */
	public function getErrOrigin():int {
		return $this->errOrigin;
	}
	
	/**
	 * Returns the names of error code constants from their error code
	 *
	 * @param int $errcode
	 * @return string
	 */
	public function errorName(int $errcode):string {
		switch ($errcode) {
			case 1:
				return 'ERR_NOT_ASCII';
			case 2:
				return 'ERR_NO_INPUT';
			case 3:
				return 'ERR_PREMATURE_END';
			case 4:
				return 'ERR_ILLEGAL_CHAR';
			case 5:
				return 'ERR_VAR_SORT';
			case 6:
				return 'ERR_OR_EXPECTED';
			case 7:
				return 'ERR_AND_EXPECTED';
			case 8:
				return 'ERR_BOOL_TERM_EXPECTED';
			case 9:
				return 'ERR_BOOL_FACTOR_EXPECTED';
			case 10:
				return 'ERR_EXPRESSION_EXPECTED';
			case 11:
				return 'ERR_TERM_EXPECTED';
			case 12:
				return 'ERR_FACTOR_EXPECTED';
			case 13:
				return 'ERR_LPAREN_EXPECTED';
			case 14:
				return 'ERR_RPAREN_EXPECTED';
			case 15:
				return 'ERR_ATOM_EXPECTED';
			case 16:
				return 'ERR_COMPARE_OPERATOR_EXPECTED';
			case 17:
				return 'ERR_BOOLEXP_EXPECTED';
			case 18:
				return 'ERR_UNKNOWN_NODE_TYPE';
			case 19:
				return 'ERR_UNKNOWN_FUNCTION';
			case 20:
				return 'ERR_UNKNOWN_MATHCONST';
			case 21:
				return 'ERR_NO_PARSETREE';
			case 22:
				return 'ERR_MISSING_VAR';
			case 23:
				return 'ERR_MISSING_VAR_VALUE';
			case 24:
				return 'ERR_VAR_NOT_NUMERIC';
			case 25:
				return 'ERR_ZERO_DENOMINATOR';
			case 26:
				return 'ERR_EMPTY_SYNTAX_TREE';
			case 27:	
				return 'ERR_EMPTY_MULTINODE_TREE';
			case 28:
				return 'ERR_TERM_MULTINODE_EXPECTED';
			default:
				return 'Unknown error '.$err;
		}
	}
	
	/**
	 * Throws an exception with code = $errCode
	 * The text is 'LmicroCAS error <name of $errcode>'. If there is an addendum ': <content of addendum>' is appended
	 * 
	 * @param int $errCode
	 * @throws \Exception
	 */
	private function error(int $errCode, $addendum = '') {
		$txt = 'LmicroCAS error '.$this->errorName($errCode);
		if ($addendum != '') {
			$txt .= ': '.$addendum;
		}
		throw new \Exception($txt, $errCode);
	}
	
	/**
	 * Returns the error position within $this->mathexp. It is set by the lexer
	 * 
	 * @return int
	 */
	public function getErrpos():int {
		return $this->errpos;
	}
	
	// Lexer -------------------------------------------------------------------------------------------------------
	
	/**
	 * Loads $this->mathexp with the string $mathexp
	 * 
	 * @param string $mathexp
	 */
	public function loadMathexp(string $mathexp) {
		$this->mathexp = $mathexp;
	}
	
	/**
	 * Sets $this->rounding, which rounds numbers at the lexer level
	 * 
	 * @param int $nrDecimals
	 */
	public function setRounding(int $nrDecimals) {
		$this->rounding = $nrDecimals;
	}
	/**
	 * Checks if $string consists of ascii characters only
	 *
	 * @param string $string
	 * @return boolean
	 */
	private function is_ascii( $string = '' ) {
		return ( bool ) ! preg_match( '/[\\x80-\\xff]+/' , $string );
	}
	
	/**
	 * Returns true if $ch is a character between 'a' and 'z', false else
	 *
	 * @param string $ch
	 * @return bool
	 */
	 private function isLowAlpha($ch):bool {
	 	return ((ord($ch) >= 97) && (ord($ch) <= 122));
	 }
	 
	 /**
	  * Returns true if $ch is a character between 'A' and 'Z', false else
	  *
	  * @param string $ch
	  * @return bool
	  */
	  private function isCapAlpha($ch):bool {
	  	return ((ord($ch) >= 65) && (ord($ch) <= 90));
	  }
	  
	/**
	 * Returns true if $ch is a character between '0' and '9', false else
	 *
	 * @param string $ch
	 * @return bool
	 */
	 private function isDigit($ch):bool {
		return ((ord($ch) >= 48) && (ord($ch) <= 57));
	 }
	 
	 /**
	  * Checks if $token is a mathematical constant 
	  * 
	  * @param int $token
	  * @return bool
	  */
	 private function isMathconst(int $token):bool {
	 	if (array_key_exists($token, self::MATHCONST_NAMES)) {
	 		return true;
	 	}
	 	return false;
	 }
	 
	 /**
	  * Checks if $token is a function
	  * 
	  * @param int $token
	  * @return bool
	  */
	 private function isFunction(int $token):bool {
	 	if (array_key_exists($token, self::FUNCTION_NAMES)) {
	 		return true;
	 	}
	 	return false;
	 }
	 /**
	  * The buffer is a string of digits and possibly a decimal point.
	  * Return value is again a string representing the number in $buffer rounded to $this->rounding decimal places
	  *
	  * @param string $buffer
	  * @return string the corrected buffer
	  */
	 private function stringRound(string $buffer): string {
	 	$roundedFloat = round(floatval($buffer),$this->rounding);
	 	$roundedString = strval($roundedFloat);
	 	return $roundedString;
	 }
	 
	 /**
	  * Adds txt to the array $this->parseTrace
	  *
	  * @param string $txt
	  */
	 private function addToParseTrace(string $txt) {
	 	$this->parseTrace[] = $txt;
	 }
	 
	 /**
	  * Implicit multiplications, denoted by '?' are inserted at lexer level in the token array.
	  * Conditions for implicit multiplication are:
	  *
	  * - mathconst followed by mathconst
	  * - mathconst followed by number
	  * - mathconst followed by variable
	  * - mathconst followed by left parentheses
	  * - mathconst followed by function
	  * 
	  * - number followed by mathconst
	  * - number followed by variable
	  * - number followed by left parentheses
	  * - number followed by function
	  * 
	  * - right parentheses followed by a mathconst
	  * - right parentheses followed by a number
	  * - right parentheses followed by a left parentheses
	  * - right parentheses followed by a variable
	  * - right parentheses followed by a function
	  * 
	  * - variable followed by a mathconst
	  * - variable followed by a left parentheses
	  * - variable followed by a number
	  *
	  * In case of single char variables there is one more case
	  *
	  * - variable followed by a variable
	  *
	  * @return array
	  */
	 private function insertImplicitMultiplications():array {
	 	$i = 0;
	 	$nrTokens = count($this->tokens);
	 	$newtokens = array();
	 	while ($i < $nrTokens) {
	 		switch ($this->tokens[$i]['tk']) {
	 			// mathconst followed by ...
	 			case self::TK_PI:
	 			case self::TK_E:
	 				$newtokens[] = $this->tokens[$i];
	 				if (($i + 1 < $nrTokens) && (
	 						$this->isMathconst($this->tokens[$i + 1]['tk']) ||
	 						($this->tokens[$i + 1]['tk'] == self::TK_NUMBER) ||
	 						($this->tokens[$i + 1]['tk'] == self::TK_VAR) ||
	 						($this->tokens[$i + 1]['tk'] == self::TK_LPAREN) ||
	 						$this->isFunction($this->tokens[$i + 1]['tk']))) {
 						// Implicit multiplication number followed by variable, left parentheses, function
 						$newtokens[] = array('tk' => self::TK_IMPMULT, 'startpos' => -1);
 					}
	 				$i++;
	 				break;
	 			// number followed by ...
	 			case self::TK_NUMBER:
	 				$newtokens[] = $this->tokens[$i];
	 				if (($i + 1 < $nrTokens) && (
	 						$this->isMathconst($this->tokens[$i + 1]['tk']) ||
	 						($this->tokens[$i + 1]['tk'] == self::TK_VAR) ||
	 						($this->tokens[$i + 1]['tk'] == self::TK_LPAREN) ||
	 						$this->isFunction($this->tokens[$i + 1]['tk']))) {
 						// Implicit multiplication number followed by variable, left parentheses, function
 						$newtokens[] = array('tk' => self::TK_IMPMULT, 'startpos' => -1);
 					}
 					$i++;
 					break;
	 			// right parentheses followed by ...
	 			case self::TK_RPAREN:
	 				$newtokens[] = $this->tokens[$i];
	 				if (($i + 1 < $nrTokens) && (
	 						$this->isMathconst($this->tokens[$i + 1]['tk']) ||
	 						($this->tokens[$i + 1]['tk'] == self::TK_NUMBER) ||
	 						($this->tokens[$i + 1]['tk'] == self::TK_LPAREN) ||
	 						($this->tokens[$i + 1]['tk'] == self::TK_VAR) ||
	 						$this->isFunction($this->tokens[$i + 1]['tk']))) {
	 					// Implicit multiplication right parentheses followed by number, left parentheses, variable, function
	 					$newtokens[] = array('tk' => self::TK_IMPMULT, 'startpos' => -1);
	 				}
	 				$i++;
	 				break;
	 			case self::TK_VAR:
	 				$newtokens[] = $this->tokens[$i];
	 				if (($i + 1 < $nrTokens) && (
	 						$this->isMathconst($this->tokens[$i + 1]['tk']) ||
	 						($this->tokens[$i + 1]['tk'] == self::TK_LPAREN) ||
	 						($this->tokens[$i +1]['tk'] == self::TK_NUMBER))) {
 						// Implicit multiplication between variable and number or variable and parenthesized expression
 						$newtokens[] = array('tk' => self::TK_IMPMULT, 'startpos' => -1);
 					}
 					if ($this->oneCharVariables) {
 						// In case of single char variables, there is an implicit multiplication between two letters
 						if (($i + 1 < $nrTokens) && (($this->tokens[$i + 1]['tk'] == self::TK_VAR))) {
 							// Implicit multiplication between variable and variable
 							$newtokens[] = array('tk' => self::TK_IMPMULT, 'startpos' => -1);
 						}
 					}
 					$i++;
 					break;
	 			default:
	 				$newtokens[] = $this->tokens[$i];
	 				$i++;
	 		}
	 	}
	 	return $newtokens;
	 }
	 
	/**
	 * Performs lexical analysis for the "mathexp" syntax
	 * Tries to split $this->mathexp into tokens and store them in $this->tokens
	 * Implicit multiplication is added where appropriate i.e.
	 *
	 * - number followed by variable
	 * - number followed by left parentheses
	 * - number followed by function
	 * - right parentheses followed by a number
	 * - right parentheses followed by a left parentheses
	 * - right parentheses followed by a variable
	 * - right parentheses followed by a function
	 * - variable followed by a left parentheses
	 *
	 * In case of single char variables there is one more case
	 *
	 * - variable followed by a variable
	 *
	 * If the lexer finds no tokens, the array $this->tokens is empty after execution of $this->lexer
	 * In case of error an exception is thrown. The text is appended in $this->errors.
	 * If the lexer is in a try block the text can still be retrieved by $this->getErrors
	 *
	 * @throws \Throwable
	 */
	public function lexer() {
		$this->setErrOrigin(self::EO_LEXER);
		if (!$this->is_ascii($this->mathexp)) {
			$this->error(self::ERR_NOT_ASCII);
		}
		$this->tokens = array();
		$this->errpos = null;
		// Get rid of any blank space
		$mathexp = preg_replace('/\s/', '', $this->mathexp);
		$length = strlen($mathexp);
		if ($length == 0) {
			$this->errpos = 0;
			$this->error(self::ERR_NO_INPUT);
		}
		$i = 0;
		while ($i < $length) {
			switch ($mathexp[$i]) {
				// Boolean operators
				case '=':
					$token = array('tk' => self::TK_EQ, 'startpos' => $i);
					$this->tokens[] = $token;
					$i++;
					break;
				case '>': // handles '>=' as well
					if ($i + 1 >= $length ) {
						$this->errpos = $i;
						$this->error(self::ERR_PREMATURE_END);
					}
					if ($mathexp[$i + 1] == '=') {
						$token = array('tk' => self::TK_GTEQ, 'startpos' => $i);
						$this->tokens[] = $token;
						$i += 2;
						break;
					} else {
						$token = array('tk' => self::TK_GT, 'startpos' => $i);
						$this->tokens[] = $token;
						$i++;
						break;
					}
				case '<': // handles '<='  and '<>' as well
					if ($i + 1 >= $length ) {
						$this->errpos = $i;
						$this->error(self::ERR_PREMATURE_END);
					}
					if ($mathexp[$i + 1] == '=') {
						$token = array('tk' => self::TK_SMEQ, 'startpos' => $i);
						$this->tokens[] = $token;
						$i += 2;
						break;
					} elseif ($mathexp[$i + 1] == '>') {
						$token = array('tk' => self::TK_NOTEQ, 'startpos' => $i);
						$this->tokens[] = $token;
						$i += 2;
						break;
					} else {
						$token = array('tk' => self::TK_SM, 'startpos' => $i);
						$this->tokens[] = $token;
						$i++;
						break;
					}
				case '&':
					$token = array('tk' => self::TK_AND, 'startpos' => $i);
					$this->tokens[] = $token;
					$i++;
					break;
				case '|':
					$token = array('tk' => self::TK_OR, 'startpos' => $i);
					$this->tokens[] = $token;
					$i++;
					break;
					// Arithmetic operators 'arop'
				case '+':
					$token = array('tk' => self::TK_PLUS, 'startpos' => $i);
					$this->tokens[] = $token;
					$i++;
					break;
				case '-':
					$token = array('tk' => self::TK_MINUS, 'startpos' => $i);
					$this->tokens[] = $token;
					$i++;
					break;
				case '*':
					$token = array('tk' => self::TK_MULT, 'startpos' => $i);
					$this->tokens[] = $token;
					$i++;
					break;
				case '/':
					$token = array('tk' => self::TK_DIV, 'startpos' => $i);
					$this->tokens[] = $token;
					$i++;
					break;
				case '^':
					$token = array('tk' => self::TK_CARET, 'startpos' => $i);
					$this->tokens[] = $token;
					$i++;
					break;
				case '(':
					$token = array('tk' => self::TK_LPAREN, 'startpos' => $i);
					$this->tokens[] = $token;
					$i++;
					break;
				case ')':
					$token = array('tk' => self::TK_RPAREN, 'startpos' => $i);
					$this->tokens[] = $token;
					$i++;
					break;
				case '[':
					$token = array('tk' => self::TK_LBOOLPAREN, 'startpos' => $i);
					$this->tokens[] = $token;
					$i++;
					break;
				case ']':
					$token = array('tk' => self::TK_RBOOLPAREN, 'startpos' => $i);
					$this->tokens[] = $token;
					$i++;
					break;
				default:
					// math constants and functions
					if ($this->isCapAlpha($mathexp[$i])) { // Handles variables and functions
						$startpos = $i;
						$buffer = '';
						while (($i < $length) && $this->isCapAlpha($mathexp[$i])) {
							$buffer .= $mathexp[$i];
							$i++;
						}
						// Check functions first
						$functoken = array_search($buffer, self::FUNCTION_NAMES);
						if ($functoken !== false) {
							// It is a function
							$token = array('tk' => $functoken, 'startpos' => $startpos);
							$this->tokens[] = $token;
						} else {
							// It is not a function, check constants. It is possibly a sequence of constants. Search the buffer from left to right
							$bufferLength = strlen($buffer);
							while ($bufferLength > 0) {
								foreach (self::MATHCONST_NAMES as $token => $fctName) {
									if (strpos($buffer, $fctName) === 0) {
										$token = array('tk' => $token, 'startpos' => $startpos);
										$startpos = $startpos + strlen($fctName);
										$this->tokens[] = $token;
										$buffer = substr($buffer, strlen($fctName));
										break;
									}
								}
								// If the buffer did not shorten, no constant was detected at the beginning of a string,
								// which cannot be anything else, than an implicit product of constants
								if (strlen($buffer) < $bufferLength) {
									// A constant was added to the tokens
									$bufferLength = strlen($buffer);
								} else {
									// Illegal capital letter
									$this->errpos = $startpos;
									$this->error(self::ERR_ILLEGAL_CHAR);
								}
							}
							// If the buffer is not empty at this point, it is neither a function nor a sequence of constants
							if (strlen($buffer) > 0) {
								// It is neither a constant nor a function
								$this->errpos = $startpos;
								$this->error(self::ERR_ILLEGAL_CHAR);
							}
						}
					} elseif ($this->isLowAlpha($mathexp[$i])) { // Handles variables
						$startpos = $i;
						$buffer = '';
						while (($i < $length) && $this->isLowAlpha($mathexp[$i])) {
							$buffer .= $mathexp[$i];
							$i++;
						}
						// It is a variable
						if ($this->oneCharVariables) {
							// Generate a token for each character
							$bufferNr = strlen($buffer);
							for ($j = 0; $j < $bufferNr; $j++) {
								$value = $buffer[$j];
								$token = array('tk' => self::TK_VAR, 'value' => $value, 'startpos' => $startpos + $j);
								$this->tokens[] = $token;
							}
							break;
						} else {
							// Generate one token for a possibly multichar variable
							$token = array('tk' => self::TK_VAR, 'value' => $buffer, 'startpos' => $startpos);
							$this->tokens[] = $token;
							break;
						}
					} elseif ($this->isDigit($mathexp[$i]))  {
						$startpos = $i;
						$buffer = '';
						$hasPoint = false;
						while (($i < $length) && $this->isDigit($mathexp[$i])) {
							$buffer .= $mathexp[$i];
							$i++;
						}
						if (($i < $length) && $mathexp[$i] == '.') {
							$hasPoint = true;
							$buffer .= '.';
							$i++;
							// At least one digit afte a decimal point is required
							if (($i < $length) && $this->isDigit($mathexp[$i])) {
								// Fill the buffer with the decimal number
								$buffer .= $mathexp[$i];
								$i++;
								// Add any number of digits after the first digit, following the point
								while (($i < $length) && $this->isDigit($mathexp[$i])) {
									$buffer .= $mathexp[$i];
									$i++;
								}
								// Do round, if rounding is required
								if ($this->rounding >= 0) {
									$buffer = $this->stringRound($buffer);
								}
								$token = array('tk' => self::TK_NUMBER, 'value' => $buffer, 'startpos' => $startpos);
								$this->tokens[] = $token;
								break;
							} else {
								$this->errpos = $i;
								$this->addError('Digit after decimal point missing');
								return false;
							}
						} else {
							// There is no point in the number
							$token = array('tk' => self::TK_NUMBER, 'value' => $buffer, 'startpos' => $startpos);
							$this->tokens[] = $token;
							break;
						}
					} else {
						$this->errpos = $i;
						$this->error(self::ERR_ILLEGAL_CHAR);
					}
			}
		}
		// Insert implicit multiplication tokens, where a multiplication must be assumed, but is not explicitly indicated by a '*'
		$this->tokens = $this->insertImplicitMultiplications();
	}
	
	// Parser --------------------------------------------------------------------------------------------------------------
		
	/**
 	 * funct		-> functionname "(" expression ")"
	 *
	 * @return array
	 */
	private function funct():array {
		$this->addToParseTrace('funct');
		$function = $this->tokens[$this->currToken]['tk'];
		$startpos = $this->tokens[$this->currToken]['startpos'];
		$this->currToken++; // Digest the functionname
		if ($this->currToken < $this->nrTokens) {
			if ($this->tokens[$this->currToken]['tk'] == self::TK_LPAREN) {
				$this->currToken++; // Digest '('
				$argument = $this->expression();
				$funct = array('ntype' => self::N_FUNCT, 'ftoken' => $function, 'child' => $argument, 'startpos' => $startpos);
				if ($this->currToken < $this->nrTokens) {
					if ($this->tokens[$this->currToken]['tk'] == self::TK_RPAREN) {
						$this->currToken++; // Digest ')'
					} else {
						$this->error(self::ERR_RPAREN_EXPECTED);
					}
				} else {
					$this->error(self::ERR_PREMATURE_END);
				}
			} else {
				$this->error(self::ERR_LPAREN_EXPECTED);
			}
		} else {
			$this->error(self::ERR_PREMATURE_END);
		}
		return $funct;
	}
	
	/**
	 * atom			-> mathconst | number | variable | funct
	 *
	 * @return array
	 */
	private function atom():array {
		$this->addToParseTrace('atom');
		if ($this->currToken < $this->nrTokens) {
			if ($this->isMathconst($this->tokens[$this->currToken]['tk'])) {
				$this->addToParseTrace('mathconstant');
				$startpos = $this->tokens[$this->currToken]['startpos'];
				$atom = array('ntype' => self::N_MATHCONST, 'ctoken' => $this->tokens[$this->currToken]['tk'], 'startpos' => $startpos);
				$this->currToken++; // Digest constant
			} elseif ($this->tokens[$this->currToken]['tk'] == self::TK_NUMBER) {
				$this->addToParseTrace('number');
				$startpos = $this->tokens[$this->currToken]['startpos'];
				$atom = array('ntype' => self::N_NUMBER, 'value' => $this->tokens[$this->currToken]['value'], 'startpos' => $startpos);
				$this->currToken++; // Digest number
			} elseif ($this->tokens[$this->currToken]['tk'] == self::TK_VAR) {
				$this->addToParseTrace('variable');
				$startpos = $this->tokens[$this->currToken]['startpos'];
				$atom = array('ntype' => self::N_VARIABLE, 'value' => $this->tokens[$this->currToken]['value'], 'startpos' => $startpos);
				$this->variables[] = $atom['value'];
				$this->currToken++; // digest variable
			} else {
				if (array_key_exists($this->tokens[$this->currToken]['tk'], self::FUNCTION_NAMES)) {
					$atom = $this->funct();
				} else {
					$this->error(self::ERR_ATOM_EXPECTED);
				}
			}
		} else {
			$this->error(self::ERR_PREMATURE_END);
		}
		return $atom;
	}
	
	/**
	 * factor		-> atom ["^" factor] | "(" expression ")"  ["^" factor]
	 *
	 * @return array
	 */
	private function factor():array {
		$this->addToParseTrace('factor');
		if (($this->currToken < $this->nrTokens) && ($this->tokens[$this->currToken]['tk'] == self::TK_LPAREN)) {
			// "(" expression ")"
			$this->currToken++; // Digest left parentheses
			if ($this->currToken < $this->nrTokens) {
				$factor = $this->expression();
				// Now we require the closing right parenthesis
				if ($this->currToken < $this->nrTokens) {
					if ($this->tokens[$this->currToken]['tk'] == self::TK_RPAREN) {
						$this->currToken++; // Digest ')'
					} else {
						$this->error(self::ERR_RPAREN_EXPECTED);
					}
				} else {
					$this->error(self::ERR_PREMATURE_END);
				}
			} else {
				$this->error(self::ERR_EXPRESSION_EXPECTED);
			}
		} else {
			// atom
			$factor = $this->atom();
		}
		if (($this->currToken < $this->nrTokens) && ($this->tokens[$this->currToken]['tk'] == self::TK_CARET)) {
			$startpos = $this->tokens[$this->currToken]['startpos'];
			$this->currToken++; // Digest '^'
			if ($this->currToken < $this->nrTokens) {
				$rightnode = $this->factor();
				$factor = array('ntype' => self::N_EXPOP, 'left' => $factor, 'right' => $rightnode, 'startpos' => $startpos);
			} else {
				$this->error(self::ERR_FACTOR_EXPECTED);
			}
		}
		return $factor;
	}
	
	/**
	 * term			-> factor { mulop factor}
	 *
	 * @return array
	 */
	private function term():array {
		$this->addToParseTrace('term');
		$term = $this->factor();
		while (($this->currToken < $this->nrTokens) && 
				(($this->tokens[$this->currToken]['tk'] == self::TK_MULT) || (
				$this->tokens[$this->currToken]['tk'] == self::TK_IMPMULT) || 
				($this->tokens[$this->currToken]['tk'] == self::TK_DIV))) {
			if ($this->tokens[$this->currToken]['tk'] == self::TK_MULT) {
				$ntype = self::N_MULOP;
			} elseif ($this->tokens[$this->currToken]['tk'] == self::TK_IMPMULT) {
				$ntype = self::N_IMPMULOP;
			} elseif ($this->tokens[$this->currToken]['tk'] == self::TK_DIV) {
				$ntype = self::N_DIVOP;
			}
			$startpos = $this->tokens[$this->currToken]['startpos'];
			$this->currToken++;
			if ($this->currToken < $this->nrTokens) {
				$rightnode = $this->factor();
				$term = array('ntype' => $ntype, 'left' => $term, 'right' => $rightnode, 'startpos' => $startpos);
			} else {
				$this->error(self::ERR_FACTOR_EXPECTED);
			}
		}
		return $term;
	}
	
	/**
	 * expression	-> ["-"] term { addop term}
	 *
	 * @return array
	 */
	private function expression():array {
		$this->addToParseTrace('expression');
		if ($this->currToken < $this->nrTokens) {
			if ($this->tokens[$this->currToken]['tk'] == self::TK_MINUS) {
				$startpos = $this->tokens[$this->currToken]['startpos'];
				$this->currToken++; // Digest unary minus				
				if ($this->currToken < $this->nrTokens) {
					$childnode = $this->term();
					$expression = array('ntype' => self::N_UNARYMINUS, 'child' => $childnode, 'startpos' => $startpos);
				} else {
					$this->error(self::ERR_EXPRESSION_EXPECTED);
				}
			} else {
				$expression = $this->term();
			}
			while (($this->currToken < $this->nrTokens) && 
					(($this->tokens[$this->currToken]['tk'] == self::TK_PLUS) || ($this->tokens[$this->currToken]['tk'] == self::TK_MINUS))) {
				if ($this->tokens[$this->currToken]['tk'] == self::TK_PLUS) {
					$ntype = self::N_ADDOP;
				} else {
					$ntype = self::N_SUBOP;
				}
				$startpos = $this->tokens[$this->currToken]['startpos'];
				$this->currToken++; // Digest addop
				if ($this->currToken < $this->nrTokens) {
					$rightnode = $this->term();
					$expression = array('ntype' => $ntype, 'left' => $expression, 'right' => $rightnode, 'startpos' => $startpos);
				} else {
					$this->error(self::ERR_TERM_EXPECTED);
				}
			}
		} else {
			// We cannot detect a potential unary minus. In any case there must be at least one token to start an expression
			$this->error(self::ERR_PREMATURE_END);
		}
		return $expression;
	}
	
	
	/**
	 * boolatom		-> expression [compareop expression]
	 *
	 * @throws \Exception
	 * @throws \Throwable
	 * @return array
	 */
	private function boolatom():array{
		$this->addToParseTrace('boolatom');
		$boolatom = $this->expression();
		if ($this->currToken < $this->nrTokens) {
			switch ($this->tokens[$this->currToken]['tk']) {
				case self::TK_EQ:
					$operator = self::N_EQOP;
					break;
				case self::TK_GT:
					$operator = self::N_GTOP;
					break;
				case self::TK_GTEQ:
					$operator = self::N_GTEQOP;
					break;
				case self::TK_SM:
					$operator = self::N_SMOP;
					break;
				case self::TK_SMEQ:
					$operator = self::N_SMEQOP;
					break;
				case self::TK_NOTEQ:
					$operator = self::N_NOTEQOP;
					break;
				default:
					// We are not in the case [boolop expression]
					$this->error(self::ERR_COMPARE_OPERATOR_EXPECTED);
					$operator = false;
			}
			if ($operator !== false) {
				$startpos = $this->tokens[$this->currToken]['startpos'];
				$this->currToken++;
				if ($this->currToken < $this->nrTokens) {
					$leftNode = $boolatom;
					$rightNode = $this->expression();
					$boolatom = array('ntype' => $operator, 'left' => $leftNode, 'right' => $rightNode, 'startpos' => $startpos);
				} else {
					$this->error(self::ERR_EXPRESSION_EXPECTED);
				}
			}
		}
		return $boolatom;
	}
	
	/**
	 * boolfactor	-> boolatom | "[" boolexp "]"
	 *
	 * @throws \Exception
	 * @throws \Throwable
	 * @return array
	 */
	private function boolfactor():array{
		$this->addToParseTrace('boolfactor');
		if (($this->currToken < $this->nrTokens) && ($this->tokens[$this->currToken]['tk'] == self::TK_LBOOLPAREN)) {
			// "[" boolexp "]"
			$this->currToken++; // Digest left parentheses
			if ($this->currToken < $this->nrTokens) {
				$boolfactor = $this->boolexp();
				// Now we require the closing right parenthesis
				if ($this->currToken < $this->nrTokens) {
					if ($this->tokens[$this->currToken]['tk'] == self::TK_RBOOLPAREN) {
						$this->currToken++; // Digest ')'
					} else {
						$this->error(self::ERR_RPAREN_EXPECTED);
					}
				} else {
					$this->error(self::ERR_PREMATURE_END);
				}
			} else {
				$this->error(self::ERR_BOOLEXP_EXPECTED);
			}
		} else {
			$boolfactor = $this->boolatom();
		}
		return $boolfactor;
	}
	
	/**
	 * boolterm		-> boolfactor { "&" boolfactor } 
	 * 
	 * @throws \Throwable
	 * @return array
	 */
	private function boolterm():array{
		$this->addToParseTrace('boolterm');
		$boolterm = $this->boolfactor();
		while (($this->currToken < $this->nrTokens) && ($this->tokens[$this->currToken]['tk'] == self::TK_AND)) {
			$startpos = $this->tokens[$this->currToken]['startpos'];
			$this->currToken++;
			if ($this->currToken < $this->nrTokens) {
				$leftNode = $boolterm;
				$rightNode = $this->boolfactor();
				$boolterm = array('ntype' => self::N_ANDOP, 'left' => $leftNode, 'right' => $rightNode, 'startpos' => $startpos);
			} else {
				// No token after '&'
				$this->error(self::ERR_BOOL_FACTOR_EXPECTED);
			}
		}
		return $boolterm;
	}
	
	/**
	 * boolexp		-> boolterm { "|" boolterm}
	 *
	 * @throws \Throwable
	 * @return array
	 */
	private function boolexp():array {
		$this->addToParseTrace('boolexp');
		$boolexp = $this->boolterm();
		while (($this->currToken < $this->nrTokens) && ($this->tokens[$this->currToken]['tk'] == self::TK_OR)) {
			$startpos = $this->tokens[$this->currToken]['startpos']; // Position of '|'
			$this->currToken++;
			if ($this->currToken < $this->nrTokens) {
				$leftNode = $boolexp;
				$rightNode = $this->boolterm();
				$boolexp = array('ntype' => self::N_OROP, 'left' => $leftNode, 'right' => $rightNode, 'startpos' => $startpos);
			} else {
				// No token after '|'
				$this->error(self::ERR_BOOLTERM_EXPECTED);
			}
		}
		return $boolexp;
	}
	/**
	 * block		->  boolexp
	 *
	 * @return array
	 */
	private function block():array {
		return $this->boolexp();
	}
	
	/**
	 * Parses the expression on $this->mathexp and builds a syntax tree stored in $this->tree.
	 * As an intermediate step $this->mathexp is first used to build an array of tokens $this->tokens.
	 * The actual parsing is done from $this->tokens.
	 */
	public function parser() {
		// Transform input $this->mathexp into an array of tokens $this->tokens first. 
		// $this->lexer throws an exception on error, so possibly $this->parser does not go beyond this point
		$this->lexer();
		// Initialize the parser
		$this->setErrOrigin(self::EO_PARSER);
		$this->variables = array();
		$this->nrTokens = count($this->tokens);
		if ($this->nrTokens == 0) {
			$this->error(self::ERR_NO_INPUT);
		}
		$this->currToken = 0;
		$this->parseTrace = array();
		$this->tree = array(); // Remove the last tree. If block() does not produce a tree, $this->tree will be empty
		$this->tree = $this->block();
		if (!sort($this->variables)) {
			$this->error(self::ERR_VAR_SORT);
		}
		// The new variables have no value, until they are set by $this->setVarValues
		$this->varValues = array();
	}
	
	// LaTeX --------------------------------------------------------------------------------------------------------------
	
	/**
	 * Checks if in a product the multiplicand must be put in parenthesis in tradidional notation
	 * 
	 * @param array $multiplicand (first factor in a product)
	 * @return bool
	 */
	private function paranthesizeMultiplicand(array $multiplicand):bool {
		if (($multiplicand['ntype'] == self::N_ADDOP) ||
			($multiplicand['ntype'] == self::N_SUBOP)) {
			return true;
		}
		return false;
	}
	
	/**
	 * Checks if in a product the multiplicator must be put in parenthesis in tradidional notation
	 * Parentheses must be put if required by operator precedence or by deviation from left associativity
	 *
	 * @param array $multiplicand (second factor in a product)
	 * @return bool
	 */
	private function paranthesizeMultiplicator(array $multiplicator):bool {
		if (($multiplicator['ntype'] == self::N_ADDOP) ||
			($multiplicator['ntype'] == self::N_SUBOP) ||
			($multiplicator['ntype'] == self::N_UNARYMINUS) ||
			// Multiplication is left associative. If right association is required, a parentheses is needed
			($multiplicator['ntype'] == self::N_MULOP) ||
			($multiplicator['ntype'] == self::N_IMPMULOP)) {
			return true;
		}
		return false;
	}
	
	/**
	 * Checks if the first operand in a boolean and must be enclosed in parentheses
	 * Parentheses must be put, if required by operator precedence
	 * 
	 * @param array $andee (first bool in AND)
	 * @return bool
	 */
	private function parenthesizeAndee(array $andee):bool {
		if ($andee['ntype'] == self::N_OROP) {
			return true;
		}
		return false;
	}
	
	/**
	 * Checks if the second operand in a boolean and must be enclosed in parentheses
	 * Parentheses must be put if required by operator precedence or by deviation from left associativity
	 *
	 * @param array $ander (second bool in AND)
	 * @return bool
	 */
	private function parenthesizeAnder(array $ander):bool {
		if (($ander['ntype'] == self::N_OROP) ||
			// Right associativity
			($ander['ntype'] == self::N_ANDOP)) {
			return true;
		}
		return false;
	}
	
	/**
	 * Checks if the second operand in a boolean or must be enclosed in parentheses
	 * Parentheses must be put if required by deviation from left associativity
	 *
	 * @param array $andee (second bool in OR)
	 * @return bool
	 */
	private function parenthesizeOrer(array $orer):bool {
		if (($orer['ntype'] == self::N_OROP)) {
			return true;
		}
		return false;
	}
	
	/**
	 * Checks if the second summand in a sum must be enclosed in parntheses
	 * Parentheses must be put if required by deviation from left associativity
	 * 
	 * @param array $addend (second summand in a sum. (The first would be augend)
	 * @return bool
	 */
	private function parenthesizeAddend(array $addend):bool {
		if (($addend['ntype'] == self::N_ADDOP) ||
			($addend['ntype'] == self::N_UNARYMINUS)) {
			return true;
		}
		return false;
	}
	
	/**
	* Checks if the second summand in a sum must be enclosed in parntheses
	* Parentheses must be put if required by deviation from left associativity
	*
	* @param array $subtrahend (second term in a difference). (The first would be minuend)
	* @return bool
	*/
	private function parenthesizeSubtrahend(array $subtrahend):bool {
		if (($subtrahend['ntype'] == self::N_ADDOP) ||
			($subtrahend['ntype'] == self::N_SUBOP) ||
			($subtrahend['ntype'] == self::N_UNARYMINUS)) {
			return true;
		}
		return false;
	}
	
	/**
	 * Checks if the child of a unary minus must be parenthesized, before prepending a '-'
	 * 
	 * @param array $child
	 * @return bool
	 */
	private function parenthesizeUnaryMinus(array $child):bool {
		if (($child['ntype'] == self::N_SUBOP) ||
			($child['ntype'] == self::N_ADDOP) ||
			($child['ntype'] == self::N_UNARYMINUS)) {
			return true;
		}
		return false;
	}
	
	/**
	 * Checks if the base of a power must be parenthesized. 
	 * This is the case except for mathconst, number variable, 
	 * The case where the function is a square root is treated at a higher level
	 * 
	 * @param array $base
	 * @return bool
	 */
	private function parenthesizeBase(array $base):bool {
		if (($base['ntype'] == self::N_MATHCONST) ||
				($base['ntype'] == self::N_NUMBER) ||
				($base['ntype'] == self::N_VARIABLE) ||
				($base['ntype'] == self::N_FUNCT)) {
			return false;
		}
		return true;
	}
	/**
	 * Recursively builds a LaTeX representation of node $node by traversing the tree.
	 * The main problem is to add parentheses, where this is appropriate for traditional notation.
	 * This is the case, where it is required by operator precedence (a+b)*c, (x*y)^2 or deviation from standard associativity (a + (b+c))
	 *
	 *
	 * @param array $node this node
	 * @return string
	 */
	public function nodeToLatex(array $node):string {
		if (empty($node)) {
			return '';
		}
		switch ($node['ntype']) {
			case self::N_MATHCONST:
				switch ($node['ctoken']) {
					case self::TK_E:
						return '\mathrm{e} ';
					case self::TK_PI:
						return '\pi ';
				}
			case self::N_NUMBER:
			case self::N_VARIABLE:
				return $node['value'];
			case self::N_IMPMULOP:
			case self::N_MULOP:
				if ($node['ntype'] == self::N_MULOP) {
					$operator = ' \cdot ';
				} else {
					$operator = '';
				}
				// newlatex
				// Node without parentheses
				$leftLatex = $this->nodeToLatex($node['left']);
				// Add parentheses, if required
				if ($this->paranthesizeMultiplicand($node['left'])) {
					// Put the left factor in parentheses
					$leftLatex = '\left('.$leftLatex.'\right)';
				}
				// Node without parentheses
				$rightLatex = $this->nodeToLatex($node['right']);
				// Add parentheses, if required
				if ($this->paranthesizeMultiplicator($node['right'])) {
					// Put the right factor in parentheses
					$rightLatex = '\left('.$rightLatex.'\right)';
				}
				// first factor second factor
				return $leftLatex.$operator.$rightLatex;
			case self::N_UNARYMINUS:
				// Node without minus and/or parentheses. These are added, where required
				$latex = $this->nodeToLatex($node['child']);
				if ($this->parenthesizeUnaryMinus($node['child'])) {
					$latex = '\left('.$latex.'\right)';
				}
				$latex = '-'.$latex;
				return $latex;
			case self::N_EXPOP:
				// Operator precedence: '^' before anything. Parentheses in base except for matconst, number, variable, function
				if ($this->parenthesizeBase($node['left'])) {
					$latex = '\left('.$this->nodeToLatex($node['left']).'\right)';
				} else {
					$latex = $this->nodeToLatex($node['left']);
				}
				$latex .= '^{'.$this->nodeToLatex($node['right']).'}';
				return $latex;
			case self::N_ADDOP:
				// Parenthesize for right associativity
				// Node without parentheses
				$leftLatex = $this->nodeToLatex($node['left']);
				// Node without parentheses
				$rightLatex = $this->nodeToLatex($node['right']);
				// Add parentheses, if required
				if ($this->parenthesizeAddend($node['right'])) {
					// Put the right factor in parentheses
					$rightLatex = '\left('.$rightLatex.'\right)';
				}
				// first factor second factor
				return $leftLatex.'+'.$rightLatex;
			case self::N_SUBOP:
				$leftLatex = $this->nodeToLatex($node['left']);
				$rightLatex = $this->nodeToLatex($node['right']);
				if ($this->parenthesizeSubtrahend($node['right'])) {
					$rightLatex = '\left('.$rightLatex.'\right)';
				}
				return $leftLatex.'-'.$rightLatex;
			case self::N_DIVOP:
				$latex = '\frac{'.$this->nodeToLatex($node['left']).'}{'.$this->nodeToLatex($node['right']).'}';
				return $latex;
			case self::N_FUNCT:
				if ($node['ftoken'] == self::TK_SQRT) {
					// Exceptionally an argument of a square root is not parenthesized
					$latex = '\sqrt{'.$this->nodeToLatex($node['child']).'}';
				} elseif ($node['ftoken'] == self::TK_ABS) {
					// Exceptionally an argument of a square root is not parenthesized
					$latex = '\left|'.$this->nodeToLatex($node['child']).'\right|';
				} else {
					switch ($node['ftoken']) {
						case self::TK_EXP:
							$latexName = '\exp';
							break;
						case self::TK_LOG:
							$latexName = '\ln';
							break;
						case self::TK_LOG10:
							$latexName = '\lg';
							break;
						case self::TK_SIN:
							$latexName = '\sin';
							break;
						case self::TK_COS:
							$latexName = '\cos';
							break;
						case self::TK_TAN:
							$latexName = '\tan';
							break;
						case self::TK_ASIN:
							$latexName = '\arcsin';
							break;
						case self::TK_ACOS:
							$latexName = '\arccos';
							break;
						case self::TK_ATAN:
							$latexName = '\arctan';
							break;
						default:
							$this->error(self::ERR_UNKNOWN_FUNCTION);
					}
					$latex = $latexName.'{\left('.$this->nodeToLatex($node['child']).'\right)}';
				}
				return $latex;
			case self::N_OROP:
				// Parenthesize for right associativity
				// Node without parentheses
				$leftLatex = $this->nodeToLatex($node['left']);
				// Node without parentheses
				$rightLatex = $this->nodeToLatex($node['right']);
				// Add parentheses, if required
				if ($this->parenthesizeOrer($node['right'])) {
					// Put the right factor in parentheses
					$rightLatex = '\left('.$rightLatex.'\right)';
				}
				// first factor second factor
				return $leftLatex.'\vee '.$rightLatex;
			case self::N_ANDOP:
				// Parenthesize for operator precedence and right associativity
				// Node without parentheses
				$leftLatex = $this->nodeToLatex($node['left']);
				// Add parentheses, if required
				if ($this->parenthesizeAndee($node['left'])) {
					// Put the left factor in parentheses
					$leftLatex = '\left('.$leftLatex.'\right)';
				}
				// Node without parentheses
				$rightLatex = $this->nodeToLatex($node['right']);
				// Add parentheses, if required
				if ($this->parenthesizeAnder($node['right'])) {
					// Put the right factor in parentheses
					$rightLatex = '\left('.$rightLatex.'\right)';
				}
				// first factor second factor
				return $leftLatex.'\wedge '.$rightLatex;
			case self::N_EQOP:
				return $this->nodeToLatex($node['left']).'='.$this->nodeToLatex($node['right']);
			case self::N_GTOP:
				return $this->nodeToLatex($node['left']).'&gt;'.$this->nodeToLatex($node['right']);
			case self::N_GTEQOP:
				return $this->nodeToLatex($node['left']).'\geq '.$this->nodeToLatex($node['right']);
			case self::N_SMOP:
				return $this->nodeToLatex($node['left']).'&lt;'.$this->nodeToLatex($node['right']);
			case self::N_SMEQOP:
				return $this->nodeToLatex($node['left']).'\leq '.$this->nodeToLatex($node['right']);
			case self::N_NOTEQOP:
				return $this->nodeToLatex($node['left']).'\neq '.$this->nodeToLatex($node['right']);
			// Additions for multinodes
			case self::NM_EXPRESSION:
				$latex = '\left[';
				foreach ($node['children'] as $child) {
					if ($child['ntype'] == self::NH_PLUS) {
						$latex .= '+';
					} else {
						$latex .= '-';
					}
					$latex .= $this->nodeToLatex($child['child']);
				}
				$latex .= '\right] ';
				return $latex;
			case self::NM_TERM:
				$latex = '\left[';
				$numerator = '';
				$denominator = '';
				foreach ($node['children'] as $child) {
					if ($child['ntype'] == self::NH_NUMERATOR) {
						if (!empty($numerator)) {
							$numerator .= ' \cdot ';
						}
						$numerator .= $this->nodeToLatex($child['child']);
					} else {
						if (!empty($denominator)) {
							$denominator .= ' \cdot ';
						}
						$denominator .= $this->nodeToLatex($child['child']);
					}
				}
				if (empty($denominator)) {
					$latex .= '{'.$numerator.'}';
				} else {
					if (empty($numerator)) {
						$numerator = '1';
					}
					$latex .= '\frac{'.$numerator.'}{'.$denominator.'}';
				}
				$latex .= '\right]';
				return $latex;
			default:
				$this->error(self::ERR_UNKNOWN_NODE_TYPE, 'type value '.$node['ntype']);
		}
	}
	
	
	/**
	 * Returns a LaTeX representation of the tree including the delimiters '\[' and '\]'
	 *
	 * @throws \Exception
	 * @return string
	 */
	public function getLatex():string {
		$this->setErrOrigin(self::EO_LATEX);
		$latex = $this->nodeToLatex($this->tree);
		return '\['.$latex.'\]';
	}
	
	/**
	 * Returns a LaTeX representation of the multinode tree including the delimiters '\[' and '\]'
	 *
	 * @throws \Exception
	 * @return string
	 */
	public function getMultiLatex():string {
		$this->setErrOrigin(self::EO_LATEX);
		$latex = $this->nodeToLatex($this->mtree);
		return '\['.$latex.'\]';
	}
	
	// Evaluator -----------------------------------------------------------------------------------------------------------------------
	
	/**
	 * Returns true, if radians are used as trigonometric units
	 * 
	 * @return bool
	 */
	public function getUseRad():bool {
		return $this->useRad;
		
	}
	
	/**
	 * Setter function for useRad
	 * 
	 * @param bool $useRad
	 */
	public function setUseRad(bool $useRad) {
		$this->useRad = $useRad;
	}
	
	/**
	 * Sets $this->varValues an array of variable values indexed by variable names
	 * 
	 * @param array $varValues
	 */
	public function setVarValues(array $varValues) {
		$this->varValues = $varValues;
		foreach ($this->varValues as $key => $value) {
			if (!in_array($key, $this->variables)) {
				$this->error(self::ERR_MISSING_VAR);
			}
		}
		foreach ($this->variables as $variable) {
			if (!array_key_exists($variable, $this->varValues)) {
				$this->error(self::ERR_MISSING_VAR_VALUE);
			}
		}
		foreach ($this->varValues as $key => $value) {
			if (!is_numeric($value)) {
				$this->error(self::ERR_VAR_NOT_NUMERIC);
			}
		}
	}
	/**
	 * Returns a numeric array of alphabetically ordered variable names
	 * 
	 * @return array
	 */
	public function getVariableNames():array {
		return $this->variables;
	}
	
	/**
	 * Returns true, if $proband is considered to be zero
	 * 
	 * @param float|string $proband
	 * @return bool
	 */
	private function isZero($proband):bool {
		return abs($proband) < self::EVAL_ZERO_BARRIER;
	}
	
	/**
	 * PHP functions can return exceptional values such as INF and NaN instead of a float. These values are filtered out. Floats are transmitted
	 * 
	 * @param mixed $fnctVal
	 * @return double
	 */
	private function filterFnctVal($fnctVal) {
		return $fnctVal;
	}
	
	private function evaluateNode(array $node) {
		switch ($node['ntype']) {
			case self::N_MATHCONST:
				switch ($node['ctoken']) {
					case self::TK_E:
						return M_E;
					case self::TK_PI:
						return M_PI;
					default:
						$this->error(self::ERR_UNKNOWN_MATHCOST);
				}
			case self::N_NUMBER:
				return $node['value'];
			case self::N_VARIABLE:
				$value = $this->varValues[$node['value']];
				if ($this->rounding >= 0) {
					$value = round($value,$this->rounding);
				}
				return $value;
			case self::N_MULOP:
			case self::N_IMPMULOP:
				return $this->evaluateNode($node['left']) * $this->evaluateNode($node['right']);
			case self::N_UNARYMINUS:
				return - $this->evaluateNode($node['child']);
			case self::N_EXPOP:
				return pow($this->evaluateNode($node['left']), $this->evaluateNode($node['right']));
			case self::N_ADDOP:
				return $this->evaluateNode($node['left']) + $this->evaluateNode($node['right']);
			case self::N_SUBOP:
				return $this->evaluateNode($node['left']) - $this->evaluateNode($node['right']);
			case self::N_DIVOP:
				$denominator = $this->evaluateNode($node['right']);
				if ($this->isZero($denominator)) {
					$this->error(self::ERR_ZERO_DENOMINATOR);
				}
				return ($this->evaluateNode($node['left'])) / $denominator;
			case self::N_FUNCT:
				switch ($node['ftoken']) {
					case self::TK_SQRT:
						$fnctVal = sqrt($this->evaluateNode($node['child']));
						break;
					case self::TK_ABS:
						$fnctVal = abs($this->evaluateNode($node['child']));
						break;
					case self::TK_EXP:
						$fnctVal = exp($this->evaluateNode($node['child']));
						break;
					case self::TK_LOG:
						$fnctVal = log($this->evaluateNode($node['child']));
						break;
					case self::TK_LOG10:
						$fnctVal = log10($this->evaluateNode($node['child']));
						break;
					case self::TK_SIN:
						$fnctVal = sin($this->evaluateNode($node['child']));
						break;
					case self::TK_COS:
						$fnctVal = cos($this->evaluateNode($node['child']));
						break;
					case self::TK_TAN:
						$fnctVal = tan($this->evaluateNode($node['child']));
						break;
					case self::TK_ASIN:
						$fnctVal = asin($this->evaluateNode($node['child']));
						break;
					case self::TK_ACOS:
						$fnctVal = acos($this->evaluateNode($node['child']));
						break;
					case self::TK_ATAN:
						$fnctVal = atan($this->evaluateNode($node['child']));
						break;
					default:
						$this->error(self::ERR_UNKNOWN_FUNCTION);
				}
				return $this->filterFnctVal($fnctVal);
			case self::N_EQOP:
				return $this->evaluateNode($node['left']) == $this->evaluateNode($node['right']);
			case self::N_GTOP:
				return $this->evaluateNode($node['left']) > $this->evaluateNode($node['right']);
			case self::N_GTEQOP:
				return $this->evaluateNode($node['left']) >= $this->evaluateNode($node['right']);
			case self::N_SMOP:
				return $this->evaluateNode($node['left']) < $this->evaluateNode($node['right']);
			case self::N_SMEQOP:
				return $this->evaluateNode($node['left']) <= $this->evaluateNode($node['right']);
			case self::N_NOTEQOP:
				return $this->evaluateNode($node['left']) != $this->evaluateNode($node['right']);
			case self::N_ANDOP:
				return ($this->evaluateNode($node['left']) && $this->evaluateNode($node['right']));
			case self::N_OROP:
				return ($this->evaluateNode($node['left']) || $this->evaluateNode($node['right']));
			default:
				$this->error(ERR_UNKNOWN_NODE_TYPE);
		}
	}
	/**
	 * Evaluates the syntax tree, returning either a boolean, in case of compares or a double in the numeric case
	 * Returns false if the syntax tree $this->tree is empty
	 * 
	 * @return bool|double | false
	 */
	public function evaluate() {
		$this->setErrOrigin(self::EO_EVALUATION);
		if (empty($this->tree)) {
			$this->error(self::ERR_NO_PARSETREE);
		}
		return $this->evaluateNode($this->tree);
	}
	
	/* Transformations from binary tree to multinode tree --------------------------------------------------------------------------
	 * 
	 * The binary tree is parsed to build up the multinode tree. 
	 * The order followed is recursive descent, just as the ordinary parser follows the order in the token array
	 * The nomenclature is the same, except for the fact, that the method names are preceeded by an 'm'
	 */ 
	
	/**
	 * Changes the sign of the holder in an array of nodes, in which each node is a holder of type NH_PLUS or NH_MINUS
	 * 
	 * @param array $terms numeric array of holders with theis subtrees
	 * @return array numeric array of flipped holders with their original subtrees
	 */
	private function flipSigns(array $terms):array {
		foreach ($terms as $key => $term) {
			if ($term['ntype'] == self::NH_PLUS) {
				$terms[$key]['ntype'] = self::NH_MINUS;
			} elseif ($term['ntype'] == self::NH_MINUS) {
				$terms[$key]['ntype'] = self::NH_PLUS;
			}
		}
		return $terms;
	}
	
	/**
	 * Traverses a binary $tree by recursive descent as long as it finds an N_ADDOP or an N_SUBOP node.
	 * 
	 * Returns a numeric array of terms. The terms are holder nodes of type NH_PLUS and NH_MINUS,
	 * which under the key 'child' hold all the subtrees, which are addends or subtrahends in the chain 
	 * of additions and subtractions on the higehest level of $tree.
	 * 
	 * If one of the final subtrees begins with a unary minus the holder node is NH_MMINUS.
	 * The sign of subtracted nodes is flipped in the recursion.
	 * Thus in the returned array there is no need for any operation symbols, because each holder provides the sign 
	 * for an algebraic addition of all returned nodes
	 * 
	 * @param array $tree binary tree 
	 * @return array numeric array of holders with their original subtrees
	 */
	private function getTerms(array $tree):array {
		$terms = array();
		if ($tree['ntype'] == self::N_ADDOP) {
			$leftTerms = $this->getTerms($tree['left']);
			$terms = array_merge($terms, $leftTerms);
			$rightTerms = $this->getTerms($tree['right']);
			$terms = array_merge($terms, $rightTerms);
		} elseif ($tree['ntype'] == self::N_SUBOP) {			
			$leftTerms = $this->getTerms($tree['left']);
			$terms = array_merge($terms, $leftTerms);
			$rightTerms = $this->getTerms($tree['right']);
			// Change the sign of all retrieved terms
			$rightTerms = $this->flipSigns($rightTerms);
			$terms = array_merge($terms, $rightTerms);
		} else {
			if ($tree['ntype'] == self::N_UNARYMINUS) {
				// Skip the unary minus and mark the term as negative
				$child = $tree['child'];
				$terms = array(array('ntype' => self::NH_MINUS, 'child' => $child, 'startpos' => -1));
			} else {
				$terms = array(array('ntype' => self::NH_PLUS, 'child' => $tree, 'startpos' => -1));
			}
		}
		return $terms;
	}
	
	/**
	 * Traverses a binary $tree by recursive descent as long as it finds an N_MULOP (or N_IMPLMOLOP) or an N_DIVOP node;
	 * 
	 * Returns a numeric array of factors. The factors are holder nodes of Type NH_NUMERATOR or NH_DENOMINATOR.
	 * The result can be viewed as a single fraction, where all NH_NUMERATOR nodes are multiplied to form the numerator and
	 * all the NH_DENOMINATOR NODES are multiplied to form the denominator.
	 * Any combinations of multiple fractions are handled correctly, since each factor is flipped between numerator and denominator,
	 * if it is a divisor inn N_DIVOP
	 * 
	 * @param array $tree binary tree
	 * @return array numeric array of holders with their original subtrees
	 */
	private function getFactors(array $tree):array {
		$factors = array();
		if (($tree['ntype'] == self::N_MULOP) || ($tree['ntype'] == self::N_IMPMULOP)) {
			$leftFactors = $this->getFactors($tree['left']);
			$factors = array_merge($factors, $leftFactors);
			$rightFactors = $this->getFactors($tree['right']);
			$factors = array_merge($factors, $rightFactors);
		} elseif ($tree['ntype'] == self::N_DIVOP) {
			$leftFactors = $this->getFactors($tree['left']);
			$factors = array_merge($factors, $leftFactors);
			$rightFactors = $this->getFactors($tree['right']);
			foreach ($rightFactors as $key => $rightFactor) {
				if ($rightFactors[$key]['ntype'] == self::NH_NUMERATOR) {
					$rightFactors[$key]['ntype'] = self::NH_DENOMINATOR;
				} elseif ($rightFactors[$key]['ntype'] == self::NH_DENOMINATOR) {
					$rightFactors[$key]['ntype'] = self::NH_NUMERATOR;
				}
			}
			$factors = array_merge($factors, $rightFactors);
		} else {
			$factors = array(array('ntype' => self::NH_NUMERATOR, 'child' => $tree, 'startpos' => -1));
		}
		return $factors;
	}
	
	private function mFactor(array $factor) {
		if ($factor['ntype'] == self::N_EXPOP) {
			$factor['left'] = $this->mExpression($factor['left']);
			$factor['right'] = $this->mExpression($factor['right']);
		} elseif ($factor['ntype'] == self::N_FUNCT) {
			$factor['child'] = $this->mExpression($factor['child']);
		} elseif ($factor['ntype'] == self::N_UNARYMINUS) {
			$factor['child'] = $this->mExpression($factor['child']);
		}
		return $factor;
	}
	
	private function mTerm(array $term):array {
		$factors = $this->getFactors($term);
		if (count($factors) > 1) {
			$termMultinode = array('ntype' => self::NM_TERM, 'startpos' => -1);
			foreach ($factors as $key => $factor) {
				$mExpression = $this->mExpression($factor['child']);
				$factors[$key]['child'] = $mExpression;
			}
			$termMultinode['children'] = $factors;
			return $termMultinode;
		} else {
			return $this->mFactor($term);
		}
	}
	
	/**
	 * Returns a multinode expression from a binary $expression.
	 * In a multinode tree, all chains of (addition, subtraction, unary minus) nodes are substituted by a single
	 * NM_EXPRESSION node, where all operations are on the same level and are just denoted by a sign. Any associations
	 * in the binary tree are lost.
	 * Similarly all chains of (multiplication, division) are substituted by a single NM_TERM node, where all operations are
	 * on a single level and the only distinction is if they belong to the numerator or the denominator
	 * The function handles complex binary trees representing an expression, by using a recursion, detecting on descent and buiding on ascent.
	 * Example: -a + 2b*(x+y) + (u+v)^(7x+1) - SIN(4z-x) yields:
	 * 
	 * 
exp
  H negative
    C variable a
  H positive
    C term
      H numerator
        C number 2
      H numerator
        C variable b
      H numerator
        C exp
          H positive
            C variable x
          H positive
            C variable y
  H positive
    C ^
      L exp
        H positive
          C variable u
        H positive
          C variable v
      R exp
        H positive
          C term
            H numerator
              C number 7
            H numerator
              C variable x
        H positive
          C number 1
  H negative
    C funct TK_SIN
      C exp
        H positive
          C term
            H numerator
              C number 4
            H numerator
              C variable z
        H negative
          C variable x
	 * 
	 * 
	 * 
	 * @param array $expression
	 * @return array
	 */
	private function mExpression(array $expression):array {
		$terms = $this->getTerms($expression);
		if (count($terms) > 1) {
			$expressionMultinode = array('ntype' => self::NM_EXPRESSION, 'startpos' => -1);
			foreach ($terms as $key => $term) {
				$mTerm = $this->mTerm($term['child']);
				$terms[$key]['child'] = $mTerm;
			}
			$expressionMultinode['children'] = $terms;
			return $expressionMultinode;
		} else {
			return $this->mTerm($expression);
		}
	}
	
	/**
	 * Returns true iff $ntype is a comparison
	 * 
	 * @param int $ntype
	 * @return bool
	 */
	private function isCompareOp(int $ntype):bool {
		if (($ntype == self::N_EQOP) ||
			($ntype == self::N_NOTEQOP) ||
			($ntype == self::N_GTOP) ||
			($ntype == self::N_GTEQOP) ||
			($ntype == self::N_SMOP) ||
			($ntype == self::N_SMEQOP)) {
			return true;
		}
		return false;
	}
	
	/**
	 * boolfactor	-> boolatom | "[" boolexp "]"
	 * boolatom		-> expression [compareop expression]
	 * 
	 * NOTE a boolfactor is automatically a boolatom, since there are no parenteses in a binary tree
	 * 
	 * @param array $node
	 * @return array
	 */
	private function mBoolfactor(array $node):array {
		$n = $node;
		if ($this->isCompareOp($node['ntype'])) {
			$n['left'] = $this->mExpression($node['left']);
			$n['right'] = $this->mExpression($node['right']);
		} else {
			$n = $this->mExpression($node);
		}
		return $n;
	}
	
	
	/**
	 * boolterm		-> boolfactor { "&" boolfactor } 
	 * 
	 * @param array $node
	 * @return array
	 */
	private function mBoolterm(array $node):array {
		$n = $node;
		if ($node['ntype'] == self::N_ANDOP) {
			$n['left'] = $this->mBoolfactor($node['left']);
			$n['right'] = $this->mBoolfactor($node['right']);
		} else {
			$n = $this->mBoolfactor($node);
		}
		return $n;
	}
	
	/**
	 * boolexp		-> boolterm { "|" boolterm}
	 * 
	 * @return array
	 */
	private function mBoolexp(array $node):array {
		$n = $node;
		if ($node['ntype'] == self::N_OROP) {
			$n['left'] = $this->mBoolterm($node['left']);
			$n['right'] = $this->mBoolterm($node['right']);
		} else {
			$n = $this->mBoolterm($node);
		}
		return $n;
	}
	
	private function mBlock(array $node):array {
		return $this->mBoolexp($node);
	}
	
	/**
	 * Fills the multinode tree $this->mtree using the binary tree $this->tree
	 */
	public function binaryToMultinode() {
		if (empty($this->tree)) {
			$this->error(self::ERR_EMPTY_SYNTAX_TREE);
		}
		$this->mtree = $this->mBlock($this->tree);
	}
	
	// Transformations from multinode tree to binary tree --------------------------------------------------------------------------
	
	/**
	 * Sort criteria of factors in terms are:
	 * 		- numerator before denominator
	 * 		- number ordered by magnitude
	 * 		- constant
	 * 		- variable in alphabetic order
	 * 
	 * @param array $a
	 * @param array $b
	 * @return number
	 */
	private static function cmpFactors($a, $b) {
		if (($a['ntype'] == self::NH_NUMERATOR) && ($b['ntype'] == self::NH_DENOMINATOR)) {
			// Numerator before denominator
			return -1;
		} elseif (($a['ntype'] == self::NH_DENOMINATOR) && ($b['ntype'] == self::NH_NUMERATOR)) {
			// Numerator before denominator
			return 1;
		} else {
			// Both are numerators or both are denominators
			$ta = $a['child']['ntype'];
			$tb = $b['child']['ntype'];
			// Numbers before anything else
			if (($ta == self::N_NUMBER) && ($tb != self::N_NUMBER)) {
				return -1;
			} elseif (($ta != self::N_NUMBER) && ($tb == self::N_NUMBER)) {
				return  1;
			} elseif (($ta == self::N_NUMBER) && ($tb == self::N_NUMBER)) {
				return floatval($a['child']['value']) > floatval($b['child']['value']);
			}
			// constants before anything else
			if (($ta == self::N_MATHCONST) && ($tb != self::N_MATHCONST)) {
				return -1;
			} elseif (($ta != self::N_MATHCONST) && ($tb == self::N_MATHCONST)) {
				return  1;
			} elseif (($ta == self::N_MATHCONST) && ($tb == self::N_MATHCONST)) {
				return 0; // No sorting within mathematical constants
			}
			// Variables in alphabetic order before anyting else
			if (($ta == self::N_VARIABLE) && ($tb != self::N_VARIABLE)) {
				return -1;
			} elseif (($ta != self::N_VARIABLE) && ($tb == self::N_VARIABLE)) {
				return  1;
			} elseif (($ta == self::N_VARIABLE) && ($tb == self::N_VARIABLE)) {
				return (ord($a['child']['value']) > ord($b['child']['value'])); // Alphabetic sort
			}
			// All other nodes have no priority
			return 0;
		}
	}
	
	private function sortTermMultinode(array $term):array {
		if (usort($term['children'],array('self','cmpFactors'))) {
			return $term;
		}
	}
	
	private function mNodeToBinary(array $mnode):array {
		$n = array();
		if ($mnode['ntype'] == self::NM_EXPRESSION) {
			$left = false;
			foreach($mnode['children'] as $key => $child) {
				if ($left === false) {
					// first summand
					if ($child['ntype'] == self::NH_PLUS) {
						$left = $this->mNodeToBinary($child['child']);
					} else {
						$left = array('ntype' => self::N_UNARYMINUS, 'child' => $this->mNodeToBinary($child['child']), 'startpos' => -1);
					}
				} else {
					// successive summand
					$right = $this->mNodeToBinary($child['child']);
					if ($child['ntype'] == self::NH_PLUS) {
						$operator = self::N_ADDOP;
					} else {
						$operator = self::N_SUBOP;
					}
					if (empty($n)) {
						$n = array('ntype' => $operator, 'left' => $left, 'right' => $right, 'startpos' => -1);
					} else {
						$n = array('ntype' => $operator, 'left' => $n, 'right' => $right, 'startpos' => -1);
					}
					$left = $n;
				}
			}
		} elseif ($mnode['ntype'] == self::NM_TERM) {
			$mnode = $this->sortTermMultinode($mnode);
			$numerator = false;
			$denominator = false;
			foreach ($mnode['children'] as $key => $child) {
				if ($child['ntype'] == self::NH_NUMERATOR) {
					if ($numerator === false) {
						$numerator = $this->mNodeToBinary($child['child']);
					} else {
						$right = $this->mNodeToBinary($child['child']);
						if (empty($numerator)) {
							$numerator = array('ntype' => self::N_MULOP, 'left' => $numerator, 'right' => $right, 'startpos' => -1);
						} else {
							$numerator = array('ntype' => self::N_MULOP, 'left' => $numerator, 'right' => $right, 'startpos' => -1);
						}
					}					
				} else {
					if ($denominator === false) {
						$denominator = $this->mNodeToBinary($child['child']);
					} else {
						$right = $this->mNodeToBinary($child['child']);
						if (empty($denominator)) {
							$denominator = array('ntype' => self::N_MULOP, 'left' => $denominator, 'right' => $right, 'startpos' => -1);
						} else {
							$denominator = array('ntype' => self::N_MULOP, 'left' => $denominator, 'right' => $right, 'startpos' => -1);
						}
					}
				}
			}
			if (empty($denominator)) {
				$n = $numerator;
			} else {
				if (empty($numerator)) {
					$numerator = array('ntype' => self::N_VARIABLE, 'value' => 1, 'startpos' => -1);
				}
				$n = array('ntype' => self::N_DIVOP, 'left' => $numerator, 'right' => $denominator, 'startpos' => -1);
			}
		} elseif ($mnode['ntype'] == self::N_FUNCT) {
			$n = array('ntype' => self::N_FUNCT, 'ftoken' => $mnode['ftoken'], 'child' => $this->mNodeToBinary($mnode['child']), 'startpos' => -1);
		} elseif ($mnode['ntype'] == self::N_EXPOP) {
			$n = array('ntype' => self::N_EXPOP, 'left' => $this->mNodeToBinary($mnode['left']), 'right' => $this->mNodeToBinary($mnode['right']), 'startpos' => -1);
		} else {
			$n = $mnode;
		}
		return $n;
	}
	
	public function multinodeToBinary() {
		$this->tree = $this->mNodeToBinary($this->mtree);
	}
	
	// Expansion (distributive law is applied wherever possible) ------------------------------------------------------------------
	
	private function isOne(array $node):bool {
		return ($node['ntype'] == self::N_NUMBER) && ($node['value'] == '1');
	}
	
	/**
	 * Native PHP unions are based on keys, and do not reindex. This function makes the union of the VALUES of arrays $a and $b
	 * as a numeric array having first the values of $a and then the values of $b. Indices have no meaning
	 *
	 * @param array $a
	 * @param array $b
	 * @return array
	 */
	private function union(array $a, array $b):array {
		$result = array();
		foreach ($a as $value) {
			$result[] = $value;
		}
		foreach ($b as $value) {
			$result[] = $value;
		}
		return $result;
	}
	
	/**
	 * $factors is a numeric array of factor holders.
	 * An array of the same holders with the same subtrees is returned, but holders are switched from numerator to denominator snd vice versa.
	 * 
	 * @param array $factors
	 * @return array
	 */
	private function flipNumDen(array $factors):array {
		foreach ($factors as $key => $factor) {
			if ($factor['ntype'] == self::NH_NUMERATOR) {
				$factors[$key]['ntype'] = self::NH_DENOMINATOR;
			} else {
				$factors[$key]['ntype'] = self::NH_NUMERATOR;
			}			
		}
		return $factors;
	}
	
	/**
	 * Extracts the numerator of an NM_TERM node.
	 * The numeator can be:
	 * 		- AN NM_TERM node with only NH_NUMERATOR holders
	 * 		- any non NM_TERM node, if only one factor was a numerator
	 * 		- a NM_NUMBER node with value 1, if all factors were denominators
	 * 
	 * @param array $term either an NM_TERM node with only NH_NUMERATOR children, or a node, which is not an NM_TERM
	 * @return array
	 */
	private function numerator(array $term):array {
		assert($term['ntype'] == self::NM_TERM, 'Term expected in numerator extraction');
		$factors = array();
		foreach ($term['children'] as $factor) {
			if ($factor['ntype'] == self::NH_NUMERATOR) {
				$factors[] = $factor;
			}
		}
		$nrFactors = count($factors);
		if ($nrFactors == 0) {
			// No numerator factors
			return array('ntype' => self::N_NUMBER, 'value' => '1', 'startpos' => -1);
		} elseif ($nrFactors == 1) {
			return $factors[0]['child'];
		} else {
			return array('ntype' => self::NM_TERM, 'children' => $factors, 'startpos' => -1);
		}
	}
	
	/**
	 * Extracts the denominator of an NM_TERM node.
	 * The numeator can be:
	 * 		- AN NM_TERM node with only NH_NUMERATOR holders
	 * 		- any non NM_TERM node, if only one factor was a denominator
	 * 		- a NM_NUMBER node with value 1, if all factors were numerators
	 *
	 * @param array $term either an NM_TERM node with only NH_NUMERATOR children, or a node, which is not an NM_TERM
	 * @return array
	 */
	private function denominator(array $term):array {
		assert($term['ntype'] == self::NM_TERM, 'Term expected in denominator extraction');
		$factors = array();
		foreach ($term['children'] as $factor) {
			if ($factor['ntype'] == self::NH_DENOMINATOR) {
				$factors[] = $factor;
			}
		}
		$nrFactors = count($factors);
		if ($nrFactors == 0) {
			// No denominator factors
			return array('ntype' => self::N_NUMBER, 'value' => '1', 'startpos' => -1);
		} elseif ($nrFactors == 1) {
			return $factors[0]['child'];
		} else {
			return array('ntype' => self::NM_TERM, 'children' => $this->flipNumDen($factors), 'startpos' => -1);
		}
	}
	
	/**
	 * Returns an NM_TERM node, which is the product of nodes $n1 and $n2.
	 * If one or both of $n1 and $n2 are themselves NM_TERM nodes a consolidation takes place.
	 * Acts only on the first level, subtrees are copied, but not handled in any way
	 * 
	 * @param array $n1
	 * @param array $n2
	 * @return array an NM_TERM node
	 */
	private function simpleProduct(array $n1, array $n2):array {
		assert(($n1['ntype'] != self::NM_EXPRESSION) && ($n2['ntype'] != self::NM_EXPRESSION), 'Improper use of simpleProduct');
		$n = array('ntype' => self::NM_TERM, 'children' => array(), 'startpos' => -1);
		if ($n1['ntype'] == self::NM_TERM) {
			if ($n2['ntype'] == self::NM_TERM) {
				// $n1 and $n2 are both product nodes. Consolidate
				$children2 = $n2['children'];
			} else {
				// $n1 is a product node, $n2 not
				$children2 = array(array('ntype' => self::NH_NUMERATOR, 'child' => $n2, 'startpos' => -1));
			}
			$n['children'] = array_merge($n1['children'], $children2);
		} else {
			$children1 = array(array('ntype' => self::NH_NUMERATOR, 'child' => $n1, 'startpos' => -1));
			if ($n2['ntype'] == self::NM_TERM) {
				// $n2 is a product node, $n1 not
				$n['children'] = array_merge($children1, $n2['children']);
			} else {
				// neither is a product node
				$children2 = array(array('ntype' => self::NH_NUMERATOR, 'child' => $n2, 'startpos' => -1));
				$n['children'] = array_merge($children1, $children2);
			}
		}
		return $n;
	}
	
	/**
	 * Returns an NM_TERM node, which is the product of nodes $n1 and $n2, applying the distributive law if one of the factors or both are a sum
	 * 
	 * @param array $n1
	 * @param array $n2
	 * @return array an NM_TERM node
	 */
	private function distributiveProduct(array $n1, array $n2):array {
		if ($n1['ntype'] == self::NM_EXPRESSION) {
			// The product will be a sum in any case
			$n = array('ntype' => self::NM_EXPRESSION, 'children' => array(), 'startpos' => -1);
			foreach ($n1['children'] as $summand1) {
				if ($n2['ntype'] == self::NM_EXPRESSION) {
					// $n1 and $n2 are both sums. Ex. (a-b)(x+y)
					foreach ($n2['children'] as $summand2) {
						if (($summand1['ntype'] == self::NH_PLUS) && ($summand2['ntype'] == self::NH_PLUS) ||
							($summand1['ntype'] == self::NH_MINUS) && ($summand2['ntype'] == self::NH_MINUS)) {
							$n['children'][] = array('ntype' => self::NH_PLUS, 'child' => $this->simpleProduct($summand1['child'], $summand2['child']));	
						} else {
							$n['children'][] = array('ntype' => self::NH_MINUS, 'child' => $this->simpleProduct($summand1['child'], $summand2['child']));
							
						}
					}
				} else {
					// $n1 is a sum, $n2 not. Ex. (a-b)SIN(z)
					$n['children'][] = array('ntype' => $summand1['ntype'], 'child' => $this->simpleProduct($summand1['child'], $n2));
				}
			}
		} else {
			if ($n2['ntype'] == self::NM_EXPRESSION) {
				$n = array('ntype' => self::NM_EXPRESSION, 'children' => array(), 'startpos' => -1);
				foreach ($n2['children'] as $summand2) {
					$n['children'][] = array('ntype' => $summand2['ntype'], 'child' => $this->simpleProduct($n1, $summand2['child']));
				}
			} else {
				// Neither is a sum
				$n = $this->simpleProduct($n1, $n2);
			}
		}
		return $n;
	}
	
	/**
	 * Applies the distributive law to an NM_term node, having only NH_NUMERATOR children.
	 * Ex. a*b(x+y)(u+v) -> abxu + abxv + abyu + abyv
	 * Expands the node, if it is not an an NM_TERM. Expands the children, before multiplying them if it is an NM_TERM
	 * 
	 * @param array $term
	 * @return array
	 */
	private function expandSimpleTerm(array $term):array {
		if ($term['ntype'] == self::NM_TERM) {
			$product = false;
			foreach ($term['children'] as $factor) {
				assert($factor['ntype'] == self::NH_NUMERATOR, 'Numerator child expected in expandSimpleTerm');
				if ($product === false) {
					$product = $this->mExpand($factor['child']);
				} else {
					$product = $this->distributiveProduct($product, $this->mExpand($factor['child']));
				}
			}
			$n = $product;
		} else {
			// The numerator has only one factor
			$n = $this->mExpand($term);
		}
		return $n;
	}
	
	private function mExpand(array $n):array {
		if ($n['ntype'] == self::NM_TERM) {
			$numerator = $this->numerator($n);
			$expnum = $this->expandSimpleTerm($numerator);
			$denominator = $this->denominator($n);
			if ($this->isOne($denominator)) {
				$n = $expnum;
			} else {
				$factors = array();
				$factors[] = array('ntype' => self::NH_NUMERATOR, 'child' => $expnum, 'startpos' => -1);
				$expden = $this->expandSimpleTerm($denominator);
				$factors[] = array('ntype' => self::NH_DENOMINATOR, 'child' => $expden, 'startpos' => -1);
				$n = array('ntype' => self::NM_TERM, 'children' => $factors, 'startpos' => -1);
			}
			/*
			$numerator = $this->numerator($n);
			// The numerator need not be an NM_TERM, it could be just a number or variable or function
			if ($numerator['ntype'] == self::NM_TERM) {
				// The numerator has more than one factor. Multyply them together
				$product = false;
				foreach ($numerator['children'] as $factor) {
					if ($product === false) {
						$product = $this->mExpand($factor['child']);
					} else {
						$product = $this->distributiveProduct($product, $this->mExpand($factor['child']));
					}
				}
				$n = $product;
			} else {
				// The numerator has only one factor
				$n = $this->mExpand($numerator);
			}
			*/
		} elseif ($n['ntype'] == self::NM_EXPRESSION) {
			// Expand the children
			$sum = array('ntype' => self::NM_EXPRESSION, 'children' => array(), 'startpos' => -1);
			foreach ($n['children'] as $summand) {
				$expandedChild = $this->mExpand($summand['child']);
				if ($expandedChild['ntype'] == self::NM_EXPRESSION) {
					if ($summand['ntype'] == self::NH_MINUS) {
						$expandedChild['children'] = $this->flipSigns($expandedChild['children']);
					}
					$sum['children'] = array_merge($sum['children'],$expandedChild['children']);
				} else {
					$child = array('ntype' => $summand['ntype'], 'child' => $expandedChild, 'startpos' => -1);
					$sum['children'] = array_merge($sum['children'],array($child));
				}
			}
			$n = $sum;
		} elseif ($n['ntype'] == self::N_FUNCT) {
			// Expand the argument
			$n = array('ntype' => self::N_FUNCT, 'ftoken' => $n['ftoken'], 'child' => $this->mExpand($n['child']), 'startpos' => -1);
		}
		return $n;
	}
	
	/**
	 * The multinode tree $this->mtree is completely expanded
	 */
	public function expand() {
		if (empty($this->mtree)) {
			$this->error(self::ERR_EMPTY_MULTINODE_TREE);
		}
		$this->mtree = $this->mExpand($this->mtree);
	}
	
	// Debugging aids --------------------------------------------------------------------------------------------------------------
	
	/**
	 * Returns a string name of token $tk
	 * 
	 * @param int $tk
	 * @throws \Exception
	 * @return string
	 */
	private function tokenName(int $tk):string {
		switch ($tk) { 
			case 1: 
				return 'TK_OR';
			case 2: 
				return 'TK_AND';
			case 3: 
				return 'TK_EQ';
			case 4: 
				return 'TK_GT';
			case 5: 
				return 'TK_GTEQ';
			case 6: 
				return 'TK_SM';
			case 7: 
				return 'TK_SMEQ';
			case 8: 
				return 'TK_NOTEQ';
			case 9: 
				return 'TK_MINUS';
			case 10: 
				return 'TK_CARET';
			case 11: 
				return 'TK_LPAREN';
			case 12: 
				return 'TK_RPAREN';
			case 13: 
				return 'TK_NUMBER';
			case 14: 
				return 'TK_VAR';
			case 15: 
				return 'TK_PLUS';
			case 16: 
				return 'TK_MULT';
			case 27: 
				return 'TK_DIV';
			case 18: 
				return 'TK_IMPMULT';
			case 19:
				return 'TK_LBOOLPAREN';
			case 20:
				return 'TK_RBOOLPAREN';
			// Mathematical constants
			case 101:
				return 'TK_PI';
			case 102:
				return 'TK_E';
			// Miscellaneous functions
			case 201:
				return 'TK_ABS';
			case 202:
				return 'TK_SQRT';
			// Exponential functions
			case 211:
				return 'TK_EXP';
			case 212:
				return 'TK_LOG';
			case 213:
				return 'TK_LOG10';
			// Trigonometric functions
			case 221:
				return 'TK_SIN';
			case 222:
				return 'TK_COS';
			case 223:
				return 'TK_TAN';
			case 224:
				return 'TK_ASIN';
			case 225: 
				return 'TK_ACOS';
			case 226:
				return 'TK_ATAN';
			default:
				throw new \Exception('Unknown token name of token '.$tk);
				
				
		}
	}
	
	/**
	 * Returns a native error message suitable for debugging.
	 * Implements a handler for exceptions thrown by $this->error
	 * Should be replaced by an external implementation for production
	 * 
	 * @param int $err
	 * @return string
	 */
	public function nativeErrorMessage(\Exception $ex):string {
		$html = $ex->getMessage();
		$errOrigin = $this->getErrOrigin();
		if ($errOrigin == self::EO_LEXER) {
			// Error detected by the lexer
			$html .= '<br>'.substr($this->mathexp, 0, $this->errpos).' <--';
		} elseif ($errOrigin == self::EO_PARSER) {
			// Error detected by the parser
			// An erroneous production could advance currToken beyond the end
			if ($this->currToken > $this->nrTokens - 1) {
				$this->currToken = $this->nrTokens - 1;
			}
			$html .= '<br>'.substr($this->mathexp, 0, $this->tokens[$this->currToken]['startpos']).' <--';
		}
		return $html;
	}
	
	/**
	 * Returns the token array $this->tokens as a text string suitable for <pre> formatting
	 * 
	 * @return string
	 */
	public function getTokensAsTxt():string {
		$txt = '';
		foreach ($this->tokens as $token) {
			$txt .= $this->tokenName($token['tk']);
			if (isset($token['value'])) {
				$txt .= ' ['.$token['value'].']';
			}
			$txt .= '  position: '.$token['startpos'];
			$txt .= self::BR;
		}
		return $txt;
	}
	
	/**
	 * Returns the parse trace i.e. the sequence of productions as a text string suitable for <pre> formatting
	 * 
	 * @return string
	 */
	public function getParseTrace():string {
		$txt = '';
		foreach ($this->parseTrace as $entry) {
			$txt .= $entry;
			$txt .= self::BR;
		}
		return $txt;
	}
	
	/**
	 * Returns a string symbol for each self::N_xx constant
	 * 
	 * @param int $ntype
	 * @return string
	 */
	private function nodeSymbol(int $ntype):string {
		switch ($ntype) {
			case self::N_NUMBER:
			  return 'number';
			case self::N_VARIABLE:
				return 'variable';
			case self::N_OROP:
				return '|';
			case self::N_ANDOP:
				return '&';
			case self::N_EQOP:
				return '=';
			case self::N_GTOP:
				return '>';
			case self::N_GTEQOP:
				return '>=';
			case self::N_SMOP:
				return '<';
			case self::N_SMEQOP;
				return '<=';
			case self::N_NOTEQOP:
				return'<>';
			case self::N_UNARYMINUS:
				return 'u-';
			case self::N_ADDOP:
				return '+';
			case self::N_SUBOP:
				return '-';
			case self::N_MULOP:
				return '*';
			case self::N_IMPMULOP:
				return '?';
			case self::N_DIVOP:
				return '/';
			case self::N_EXPOP:
				return '^';
			case self::N_MATHCONST:
				return 'mathconst';
			case self::N_FUNCT:
				return 'funct';
			case self::NM_EXPRESSION:
				return 'exp';
			case self::NH_PLUS:
				return 'positive';
			case self::NH_MINUS:
				return 'negative';
			case self::NM_TERM:
				return 'term';
			case self::NH_NUMERATOR:
				return 'numerator';
			case self::NH_DENOMINATOR:
				return 'denominator';
			default:
				return 'Unknown symbol '.$ntype;
		}
	}
	
	/**
	 * Returns one line of text for a node terminated with a line break self::BR
	 * 
	 * @param array $node
	 * @param string $filiation a string transmitted by the father node, which is prepended to the string generated by $node itself
	 * @param int $depth the number of tabs preceeding the node description.
	 * @return string
	 */
	private function nodeAsTxt(array $node, string $filiation, int $depth):string {
		$txt = '';
		for ($i = 0; $i < $depth; $i++) {
			$txt .= self::TAB;
		}
		$txt .= $filiation.$this->nodeSymbol($node['ntype']);
		if (isset($node['value'])) {
			$txt .= ' '.$node['value'];
		}
		if (isset($node['ftoken'])) {
			$txt .= ' '.$this->tokenName($node['ftoken']);
		}
		if (isset($node['ctoken'])) {
			$txt .= ' '.$this->tokenName($node['ctoken']);
		}
		$txt .= self::BR;
		if (isset($node['left'])) {
			$txt .= $this->nodeAsTxt($node['left'], 'L ', $depth + 1);
		}
		if (isset($node['right'])) {
			$txt .= $this->nodeAsTxt($node['right'], 'R ', $depth + 1);
		}
		if (isset($node['child'])) {
			$txt .= $this->nodeAsTxt($node['child'], 'C ', $depth + 1);
		}
		if (isset($node['children'])) {
			foreach ($node['children'] as $child) {
				$txt .= $this->nodeAsTxt($child, 'H ', $depth + 1);
			}
		}
		return $txt;
	}
	/**
	 * Returns a representation of the syntaxTree $this->tree as a text string suitable for <pre> formatting
	 * 
	 * @return string
	 */
	public function getSyntaxTreeAsTxt():string {
		return $this->nodeAsTxt($this->tree, '', 0);
	}
	
	/**
	 * Returns a representation of the multinode tree $this->mtree as text string suitable for <pre> formatting
	 * 
	 * @return string
	 */
	public function getMultinodeTreeAsTxt():string {
		return $this->nodeAsTxt($this->mtree, '', 0);
	}
}