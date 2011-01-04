<?php
/*
 * parser.php
 *
 * Copyright 2009, 2010 Stephen Paul Ierodiaconou
 *
 * This file is part of Objective-PHP <http://www.atimport.net/>.
 *
 * Objective-PHP is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Objective-PHP is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with Objective-PHP.  If not, see <http://www.gnu.org/licenses/>.
*/

namespace ObjPHP;

// State Constants
const S_END                     = 1000;
const S_FIRST                   = 1;
const S_START                   = 0;

// Error Codes
const PARSE_ERR                             = 1001;
const PARSE_ERR_UNEX_EOF                    = 1002;
const PARSE_ERR_UNEX_CHAR                   = 1003;
const PARSE_ERR_UNEX_STRING                 = 1004;
const PARSE_ERR_UNDEF_RECEIVER              = 1005;

const PARSE_ERR_IMP_NOMETHODNAME            = 1101;
const PARSE_ERR_IMP_CLASSEXISTS             = 1102;
const PARSE_ERR_IVAR_INHER_UNDEF_PARENT     = 1103;
const PARSE_ERR_PROTOCOL_CONFORMANCE        = 1104;

const PARSE_ERR_CAT_UNDEF_PROTOCOL          = 1201;
const PARSE_ERR_CAT_UNDEF_CLASS             = 1202;

// Parser Internal Constant
const PARSER_CLASSCLASS_PREFIX          = '_opClass_';
const PARSER_METACLASS_PREFIX           = '_opMeta_';
const PARSER_INSTCLASS_PREFIX           = '';
const PARSER_PROTOCOLCLASS_PREFIX       = '_opProtocol_Class_';
const PARSER_PROTOCOLMETA_PREFIX        = '_opProtocol_Meta_';
const PARSER_PROTOCOLINST_PREFIX        = '_opProtocol_';
const PARSER_CATEGORYMETHOD_PREFIX      = '_opCat_';
const PARSER_ROOTOBJECT_NAME            = 'MKObject';
const PARSER_ROOTPROTOCOLOBJECT_NAME    = 'Protocol';
const PARSER_BASERUNTIMECLASS           = '\ObjPHP\_runtimeclass';
const PARSER_BASECLASS                  = '\ObjPHP\_class';
const PARSER_BASERUNTIMEPROTOCOLCLASS   = '\ObjPHP\_runtimeclass';
const PARSER_BASEPROTOCOLCLASS          = '\ObjPHP\_protocol';
const PARSER_CLASSTHISREFNAME           = '$_op_obj';
const PARSER_CLASSPARAMOBJNAME          = '$_op_params';

const PARSER_USE                = 1;
const PARSER_LEAVE              = 2;
const PARSER_ERROR              = 0;

const PARSER_CLASS              = 0;
const PARSER_CATEGORY           = 1;
const PARSER_PROTOCOL           = 2;
const PARSER_BRACKETSYNTAX      = 3;

// Parser
class Parser
{
    private $lastErrorCode;
    private $lastError = "";

    private $importTable = array();

    private $tokens;

    private $classes = array();
    private $protocols = array();

    function __construct($tokenizer = null)
    {
        if ($tokenizer)
            $this->tokens = $tokenizer;
    }

    public function setTokens($tokenizer)
    {
        $this->tokens = $tokenizer;
    }

    public function getTokens()
    {
        return $this->tokens;
    }

    public function getLastError()
    {
        return array("code" => $this->lastErrorCode, "text" => $this->lastError);
    }

    public function parserError($msg, $code)
    {
        $this->lastErrorCode = $code;
        $this->lastError = "Parser Error: $msg";
        if(defined('_DEBUG_'))
            _objphp_print_trace();
        throw new \ObjPHP\ParseException(null, $msg, $code);
    }

    public function syntaxError($t, $msg="", $code=-1)
    {
        $this->lastErrorCode = $code;
        $this->lastError = "Syntax Error: $msg (Line: ".$t[3]." token: '".$t[1]."' '".$t[2]."')";
        if(defined('_DEBUG_'))
            _objphp_print_trace();
        throw new \ObjPHP\ParseException($t, $msg, $code, null);
    }

    public function readImport($fileName, $rel)
    {
        // check if already imported
        $mode = ($rel)?(FILE_TEXT):(FILE_USE_INCLUDE_PATH | FILE_TEXT);
        $file = @file_get_contents($fileName, $mode);
        $unique = sha1($file);
        if (array_key_exists($unique, $this->importTable))
            return false;
        else
            $this->importTable[$unique] = true;

        if ($file===false)
            throw new \ObjPHP\CountableException("File to load '$fileName' could not be found. ".(($rel)?("The search path was relative to working directory."):("The search path absolute or the include paths.")));

        // Add close tag as by default enviroment is in HTML mode
        return "?>".$file;
    }

    // For compilation the opening tag should be retained so set $retainOpeningTag to true (its
    // off by default as eval doesnt like it
    public function parse($tokenizer = null, $runtimeimport = false)
    {
        $source = "";

        $this->startTimer();

        if ($tokenizer)
            $this->tokens = $tokenizer;

        if (!$this->tokens)
        {
            $this->parserError("Please specify a tokenizer.", PARSE_ERR);
        }
        // get first token opentag
        $t = $this->tokens->current();

        do
        {
            $useToken = false;
            switch ($t[0])
            {
                case T_COMMENT:
                    $useToken = PARSER_USE;
                    break;

                case T_OBJPHP_IMPORT:
                    $code = $this->ruleImport($t);
                    if (is_string($code))
                        $source .= $code;
                    $useToken = PARSER_LEAVE;
                    break;

                case T_OBJPHP_PROTOCOL:
                    $source .= $this->ruleProtocol($t);
                    $useToken = PARSER_LEAVE;
                    break;

                case T_OBJPHP_IMPLEMENTATION:
                    $source .= $this->ruleImplementation($t);
                    $useToken = PARSER_LEAVE;
                    break;

                case T_OBJPHP_PHP:
                    $source .= $this->rulePHPBlock($t);
                    $useToken = PARSER_LEAVE;
                    break;

                case T_WHITESPACE:
                    $source .= $t[2];
                    $useToken = PARSER_USE;
                    break;

                default:
                    if ($runtimeimport)
                        $source .= $this->ruleExpression($t, false, true, true);
                    else
                        $source .= $this->ruleExpression($t);
                    $useToken = PARSER_LEAVE;

            }

            if ($useToken == PARSER_USE)
                $t = $this->tokens->moveNext();
            else if ($useToken == PARSER_LEAVE)
                $t = $this->tokens->current();
            else
                $this->syntaxError($t, "Unexpected character in expression in state $s: ",PARSE_ERR_UNEX_CHAR);

        }
        while($t != false);

        // TODO: php -l file on result? php_check_syntax was deprecated

        $this->stopTimer();

        $source = $source . "//Generated: ".date('l jS \of F Y h:i:s A')." - Parse time: ".$this->getTime()."\n";

        return $source;
    }

    // *********************************************************************************************
    // Production rules

    // @implementation
    private function ruleImplementation($firstToken)
    {
        // consume and build until an @end is found or EOF
        $s = S_START;

        $className = false;
        $parentClassName = false;

        $iVarVis = false;
        $iVarName = false;
        $iVarType = false;
        $iVarInitialValue = false;
        $iVarAccessors = false;

        $t = $firstToken;
        do
        {
            $useToken = PARSER_ERROR;

            switch ($t[0])
            {
                case T_COMMENT:
                case T_WHITESPACE:
                    $useToken = PARSER_USE;
                    break;

                case T_OBJPHP_IMPLEMENTATION:
                    if ($s == S_START)
                    {
                        $s = S_FIRST;
                        $useToken = PARSER_USE;
                    }
                    break;

                case '<':
                    if ($s == 2 || $s == 4)
                    {
                        if ($s == 2)
                        {
                            if ($this->reflectionClassExists($className))
                                $this->syntaxError(null, "Class '$className' already exists! Specified in @implementation: ",PARSE_ERR_IMP_CLASSEXISTS);
                            $this->reflectionClassAdd($className);
                        }
                        $s = 20;

                        $list = $this->ruleCommaSeparatedList($t, '<', '>');

                        foreach($list as $pName)
                        {
                            if (!$this->reflectionProtocolExists($pName))
                                $this->syntaxError(null, "Protocol '$pName' does not exists! Specified in @implementation: ",PARSE_ERR_IMP_CLASSEXISTS);

                            $this->reflectionClassAddProtocol($className, $pName);
                        }

                        $useToken = PARSER_USE;
                    }
                    break;

                case ':':
                    if ($s == 2)
                    {
                        if ($s == 2)
                        {
                            if ($this->reflectionClassExists($className))
                                $this->syntaxError(null, "Class '$className' already exists! Specified in @implementation: ",PARSE_ERR_IMP_CLASSEXISTS);
                            $this->reflectionClassAdd($className);
                        }
                        $s = 3;
                        $useToken = PARSER_USE;
                    }
                    break;

                case '(':
                    if ($s == 2)
                    {
                        // category
                        return $this->ruleCategory($t,$className);
                    }
                    break;

                case '{':
                    if ($s == 2 || $s == 4 || $s == 20)
                    {
                        if ($s == 2)
                        {
                            if ($this->reflectionClassExists($className))
                                $this->syntaxError(null, "Class '$className' already exists! Specified in @implementation: ",PARSE_ERR_IMP_CLASSEXISTS);
                            $this->reflectionClassAdd($className);
                        }
                        // add ivars
                        $s = 40;
                        $useToken = PARSER_USE;
                    }
                    break;

                // Not Keywords, ie. classes and protocols must have non PHP keyword names.
                case T_STRING:
                    if ($s == S_FIRST)
                    {
                        // class name
                        $className .= $t[2];
                        $s = 2;
                        $useToken = PARSER_USE;
                    }
                    else if ($s == 3)
                    {
                        // parent name
                        $parentClassName = $t[2];
                        if (!$this->reflectionClassExists($parentClassName))
                            $this->syntaxError(null, "Class '$parentClassName' does not exist! Make sure it is already defined. Specified in @implementation of class '$className': ",PARSE_ERR_IMP_CLASSEXISTS);
                        $this->reflectionClassSetParent($className, $parentClassName);
                        $s = 4;
                        $useToken = PARSER_USE;
                    }
                    else if ($s == 40 || $s == 41)
                    {
                        $iVarName = $t[2];
                        $s = 42;
                        $useToken = PARSER_USE;
                    }
                    else if ($s == 42 && !$this->reflectionClassPropertyType($className, $iVarName) )
                    {
                        // another string on ivar, hence prev thing was actually a type, n this is name
                        $iVarType = $iVarName;
                        $iVarName = $t[2];
                        $s = 42;
                        $useToken = PARSER_USE;
                    }
                    else if ($s == 43)
                    {
                        // initval as string
                        $iVarInitialValue = $t[2];
                        $s = 44;
                        $useToken = PARSER_USE;
                    }
                    break;

                case T_OBJPHP_PUBLIC:
                    if ($s == 40)
                    {
                        $iVarVis = "public";
                        $s = 41;
                        $useToken = PARSER_USE;
                    }
                    break;

                case T_OBJPHP_PRIVATE:
                    if ($s == 40)
                    {
                        $iVarVis = "private";
                        $s = 41;
                        $useToken = PARSER_USE;
                    }
                    break;

                case T_OBJPHP_PROTECTED:
                    if ($s == 40)
                    {
                        $iVarVis = "protected";
                        $s = 41;
                        $useToken = PARSER_USE;
                    }
                    break;

                case T_OBJPHP_ACCESSORS:
                    if ($s == 42 || $s == 44)
                    {
                        $iVarAccessors = true;
                        $s = 45;
                        $useToken = PARSER_USE;
                    }
                    break;

                case T_STRING_VARNAME:
                case T_NUM_STRING:
                case T_ENCAPSED_AND_WHITESPACE:
                case T_CONSTANT_ENCAPSED_STRING:
                    if ($s == 43)
                    {
                        // initval as string
                        $iVarInitialValue = $t[2];
                        $s = 44;
                        $useToken = PARSER_USE;
                    }
                    break;

                case T_OBJPHP_NIL:
                case T_OBJPHP_OBJNIL:
                    if ($s == 43)
                    {
                        // initial as number
                        $iVarInitialValue = $this->terminalNil($t);
                        $s = 44;
                        $useToken = PARSER_USE;
                    }
                    break;

                case T_OBJPHP_YES:
                case T_OBJPHP_NO:
                    if ($s == 43)
                    {
                        // initial as number
                        $iVarInitialValue = $this->terminalYesNo($t);
                        $s = 44;
                        $useToken = PARSER_USE;
                    }
                    break;

                case T_OBJPHP_SELECTOR:
                    if ($s == 43)
                    {
                        // initial as number
                        $iVarInitialValue = $this->ruleSelector($t);
                        $s = 44;
                        $useToken = PARSER_USE;
                    }
                    break;

                // = array( ... ) is valid initial
                case T_ARRAY:
                    if ($s == 43)
                    {
                        // initial as number
                        $iVarInitialValue = $this->ruleExpression($t, true, false, false);
                        $s = 44;
                        $useToken = PARSER_LEAVE;
                    }
                    break;

                case T_LNUMBER:
                    if ($s == 43)
                    {
                        // initial as number
                        $iVarInitialValue = intval($t[2]);
                        $s = 44;
                        $useToken = PARSER_USE;
                    }
                    break;
                case T_DNUMBER:
                    if ($s == 43)
                    {
                        // initial as number
                        $iVarInitialValue = floatval($t[2]);
                        $s = 44;
                        $useToken = PARSER_USE;
                    }
                    break;

                case '=':
                    if ($s == 42)
                    {
                        // init value of ivar, must be a constant, ie either a string or number
                        $s = 43;
                        $useToken = PARSER_USE;
                    }
                    break;

                case ';':
                    if ($s == 42 || $s == 44 || $s == 45)
                    {
                        // end of ivar

                        // FIXME: accessors are currently always both get and set.
                        $this->reflectionClassAddProperty($className, $iVarName, $iVarVis, $iVarType, $iVarInitialValue, $iVarAccessors, $iVarAccessors);

                        $iVarVis = false;
                        $iVarName = false;
                        $iVarType = false;
                        $iVarInitialValue = false;
                        $iVarAccessors = false;
                        $s = 40;
                        $useToken = PARSER_USE;
                    }
                    break;

                case '-':
                    if ($s == 2 || $s == 4 || $s == 20)
                    {
                        if ($s == 2)
                        {
                            if ($this->reflectionClassExists($className))
                                $this->syntaxError(null, "Class '$className' already exists! Specified in @implementation: ",PARSE_ERR_IMP_CLASSEXISTS);
                            $this->reflectionClassAdd($className);
                        }
                        $methodInfo = $this->ruleMethodDeclaration('class', $t, $className);
                        $methodSource = $this->ruleFunctionBlock($this->tokens->current(), true, true, $methodInfo);
                        $this->reflectionClassAddMethod($className, $methodInfo, $methodSource, _METHOD_CLASS);
                        $s = 20;
                        $useToken = PARSER_LEAVE;
                    }
                    break;

                case '+':
                    if ($s == 2 || $s == 4 || $s == 20)
                    {
                        if ($s == 2)
                        {
                            if ($this->reflectionClassExists($className))
                                $this->syntaxError(null, "Class '$className' already exists! Specified in @implementation: ",PARSE_ERR_IMP_CLASSEXISTS);
                            $this->reflectionClassAdd($className);
                        }
                        $methodInfo = $this->ruleMethodDeclaration('class', $t, $className);
                        $methodSource = $this->ruleFunctionBlock($this->tokens->current(), false, true, $methodInfo);
                        $this->reflectionClassAddMethod($className, $methodInfo, $methodSource, _METHOD_CLASS);
                        $s = 20;
                        $useToken = PARSER_LEAVE;
                    }
                    break;

                case '}':
                    if ($s == 40)
                    {
                        $s = 20;
                        $useToken = PARSER_USE;
                    }
                    break;

                case ',':
                    if ($s == 42 || $s == 44)
                    {
                        // end of ivar
                        $this->reflectionClassAddProperty($className, $iVarName, $iVarVis, $iVarType, $iVarInitialValue);

                        //$iVarVis = false; vis inherits from first
                        $iVarName = false;
                        //$iVarType = false; type inherits
                        $iVarInitialValue = false;
                        $s = 41;
                        $useToken = PARSER_USE;
                    }
                    break;

                case T_OBJPHP_END:
                    if ($s == 2 || $s == 4 || $s == 20)
                    {
                        $s = S_END;
                        $useToken = PARSER_USE;
                    }
                    break;

                case T_VARIABLE:
                    if ($s == 40 || $s == 41)
                    {
                        $iVarName = substr($t[2],1); // remove $
                        $s = 42;
                        $useToken = PARSER_USE;
                    }
                    else if ($s == 42 && !$this->reflectionClassPropertyType($className, $iVarName) )
                    {
                        // another string on ivar, hence prev thing was actually a type, n this is name
                        $iVarType = $iVarName;
                        $iVarName = substr($t[2],1); // remove $
                        $s = 42;
                        $useToken = PARSER_USE;
                    }

                default:
                    if (($s == 40 || $s == 41) && $this->terminalIsPHPKeyword($t))
                    {
                        $iVarName = $t[2];
                        $s = 42;
                        $useToken = PARSER_USE;
                    }
                    // FIXME: is this valid syntax????????????
                    else if (($s == 40 || $s == 41) && $this->terminalIsPHPCastKeyword($t))
                    {
                        $iVarName = $t[2];
                        $s = 42;
                        $useToken = PARSER_USE;
                    }
                    else if ($s == 42 && !$this->reflectionClassPropertyType($className, $iVarName) && $this->terminalIsPHPKeyword($t))
                    {
                        // another string on ivar, hence prev thing was actually a type, n this is name
                        $iVarType = $iVarName;
                        $iVarName = $t[2];
                        $s = 42;
                        $useToken = PARSER_USE;
                    }
                    break;
            }

            if ($useToken == PARSER_USE)
                $t = $this->tokens->moveNext();
            else if ($useToken == PARSER_LEAVE)
                $t = $this->tokens->current();
            else
                $this->syntaxError($t, "Unexpected character in @implementation  of '$className' in state $s: ",PARSE_ERR_UNEX_CHAR);
        }
        while( $s < S_END && $t);

        if ($s < S_END && !$t)
            $this->syntaxError($firstToken, "Unexpected end of file while parsing @implementation of '$className': ",PARSE_ERR_UNEX_EOF);

        // Generate source
        $source = $this->generateClassSource($className, $parentClassName);

        return $source;
    }

    // @protocol
    private function ruleProtocol($firstToken)
    {
        // consume and build until an @end is found or EOF
        // OR
        // @protocol(protocolname) and return ->getInstance on protocol object
        $s = S_START;

        $protocolAccessor = false;
        $protocolName = false;
        $source = array();

        $t = $firstToken;
        do
        {
            $useToken = PARSER_ERROR;

            switch ($t[0])
            {
                case T_COMMENT:
                case T_WHITESPACE:
                    $useToken = PARSER_USE;
                    break;

                case T_OBJPHP_PROTOCOL:
                    if ($s == S_START)
                    {
                        $s = S_FIRST;
                        $useToken = PARSER_USE;
                    }
                    break;

                case T_STRING:
                    if ($s == S_FIRST)
                    {
                        $s = 2;
                        $protocolName = $t[2];

                        if ($this->reflectionProtocolExists($protocolName))
                            $this->syntaxError(null, "Protocol '$protocolName' already exists! Specified in @protocol at line ".$t[3], PARSE_ERR_IMP_CLASSEXISTS);
                        if ($this->reflectionClassExists(PARSER_PROTOCOLINST_PREFIX.$protocolName))
                            $this->syntaxError(null, "Protocol '$protocolName' already exists as a Class name! Specified in @protocol at line ".$t[3], PARSE_ERR_IMP_CLASSEXISTS);

                        $this->reflectionProtocolAdd($protocolName);
                        $this->reflectionClassAdd(PARSER_PROTOCOLINST_PREFIX.$protocolName);
                        $this->reflectionClassSetParent(PARSER_PROTOCOLINST_PREFIX.$protocolName, PARSER_ROOTPROTOCOLOBJECT_NAME);
                        $useToken = PARSER_USE;
                    }
                    else if ($s == 10)
                    {
                        $protocolName = $t[2];
                        $s = 11;
                        $useToken = PARSER_USE;
                    }
                    break;

                case '(':
                    if ($s == S_FIRST)
                    {
                        $s = 10;
                        $useToken = PARSER_USE;
                    }
                    break;

                case ')':
                    if ($s == 11)
                    {
                        $protocolAccessor = true;
                        $s = S_END;
                        $useToken = PARSER_USE;
                    }
                    break;

                case '<':
                    if ($s == 2)
                    {
                        $s = 5;
                        $list = $this->ruleCommaSeparatedList($t, '<', '>');

                        foreach ($list as $protocol)
                        {
                            if (!$this->reflectionProtocolExists($protocol))
                                $this->syntaxError(null, "Protocol '$protocol' does not exist! Specified in @protocol inheritance list for '$protocolName' at line ".$t[3],PARSE_ERR_IMP_CLASSEXISTS);

                            $this->reflectionProtocolAddProtocol($protocolName, $protocol);
                        }

                        $useToken = PARSER_USE;
                    }
                    break;

                case '-':
                    if ($s == 2 || $s == 5)
                    {
                        $methodInfo = $this->ruleMethodDeclaration('protocol', $t, $protocolName);
                        $this->reflectionProtocolAddMethod($protocolName, $methodInfo);
                        $s = 2;
                        $useToken = PARSER_LEAVE;
                    }
                    break;

                case '+':
                    if ($s == 2 || $s == 5)
                    {
                        $methodInfo = $this->ruleMethodDeclaration('protocol', $t, $protocolName);
                        $this->reflectionProtocolAddMethod($protocolName, $methodInfo);
                        $s = 2;
                        $useToken = PARSER_LEAVE;
                    }
                    break;

                case T_OBJPHP_END:
                    if ($s == 2 || $s == 5)
                    {
                        $s = S_END;
                        $useToken = PARSER_USE;
                    }
                    break;
            }

            if ($useToken == PARSER_USE)
                $t = $this->tokens->moveNext();
            else if ($useToken == PARSER_LEAVE)
                $t = $this->tokens->current();
            else
                $this->syntaxError($t, "Unexpected character in @protocol of '$protocolName' in state $s: ",PARSE_ERR_UNEX_CHAR);
        }
        while( $s < S_END && $t);

        if ($s < S_END && !$t)
            $this->syntaxError($firstToken, "Unexpected end of file while parsing @protocol: ",PARSE_ERR_UNEX_EOF);

        if (!$protocolAccessor)
        {
            return $this->generateProtocolSource($protocolName);
        }
        else
        {
            return "$protocolName::getInstance()";
        }
    }

    // @category
    private function ruleCategory($firstToken, $className)
    {
        $s = S_START;

        $source = "";
        $catName = false;

        $t = $firstToken;

        do
        {
            $useToken = PARSER_ERROR;
            switch ($t[0])
            {

                case T_WHITESPACE:
                case T_COMMENT:
                    $useToken = PARSER_USE;
                    break;

                case '(':
                    if ($s == S_START)
                    {
                        $s = S_FIRST;
                        $useToken = PARSER_USE;
                    }
                    break;

                case ')':
                    if ($s == 2)
                    {
                        $s = 3;
                        $useToken = PARSER_USE;
                    }
                    break;

                case '<':
                    if ($s == 3)
                    {
                        $s = 4;

                        $list = $this->ruleCommaSeparatedList($t, '<', '>');

                        if ($list)
                        {
                            foreach ($list as $pName)
                            {
                                if (!$this->reflectionProtocolExists($pName))
                                    $this->syntaxError(null, "Protocol '$pName' does not exists! Specified in category '$catName' @implementation at line ".$t[3],PARSE_ERR_CAT_UNDEF_PROTOCOL);

                                $this->reflectionClassCategoryAddProtocol($className, $catName, $pName);
                            }
                        }

                        $useToken = PARSER_USE;
                    }
                    break;

                case '-':
                    if ($s == 3 || $s == 4)
                    {
                        $methodInfo = $this->ruleMethodDeclaration('category', $t, $catName);
                        $methodSource = $this->ruleFunctionBlock($this->tokens->current(), true, true, $methodInfo);
                        $this->reflectionClassCategoryAddMethod($className, $catName, $methodInfo, $methodSource, _METHOD_CAT);

                        $s = 4;
                        $useToken = PARSER_LEAVE;
                    }
                    break;

                case '+':
                    if ($s == 3 || $s == 4)
                    {
                        $methodInfo = $this->ruleMethodDeclaration('category', $t, $catName);
                        $methodSource = $this->ruleFunctionBlock($this->tokens->current(), false, true, $methodInfo);
                        $this->reflectionClassCategoryAddMethod($className, $catName, $methodInfo, $methodSource, _METHOD_CAT);

                        $s = 4;
                        $useToken = PARSER_LEAVE;
                    }
                    break;

                case T_STRING:
                    if ($s == S_FIRST)
                    {
                        $s = 2;
                        $catName = $t[2];
                        if (!$this->reflectionClassExists($className))
                            $this->syntaxError($t, "Class '$className' does not exist! You are attempting to add a category '$catName' to an undefined class. Specified in @implementation at line ".$t[3],PARSE_ERR_CAT_UNDEF_CLASS);

                        $this->reflectionClassAddCategory($className, $catName);

                        $useToken = PARSER_USE;
                    }
                    break;


                case T_OBJPHP_END:
                    if ($s == 3 || $s == 4)
                    {
                        $s = S_END;
                        $useToken = PARSER_USE;
                    }
                    break;
            }

            if ($useToken == PARSER_USE)
                $t = $this->tokens->moveNext();
            else if ($useToken == PARSER_LEAVE)
                $t = $this->tokens->current();
            else
                $this->syntaxError($t, "Unexpected character in category @implementation in state $s: ",PARSE_ERR_UNEX_CHAR);
        }
        while( $s < S_END && $t);

        if ($s < S_END && !$t)
            $this->syntaxError($firstToken, "Unexpected end of file while parsing category @implementation: ",PARSE_ERR_UNEX_EOF);

        $source = $this->generateCategorySource($className, $catName);

        return $source;
    }

    // Parse anything between { and }
    private function ruleFunctionBlock($firstToken, $convertSelf = false,$convertThis = false, $methodInfo = null)
    {
        $s = S_START;

        $function = "";

        $t = $firstToken;
        do
        {
            $useToken = PARSER_ERROR;

            switch ($t[0])
            {
                case T_OBJPHP_IMPORT:
                    if ($s == S_FIRST)
                    {
                        $function .= $this->ruleImport($t, $convertSelf, $convertThis);
                        $useToken = PARSER_LEAVE;
                    }
                    break;

                case '{':
                    if ($s == S_START)
                    {
                        if ($methodInfo === null)
                        {
                            $function .= $t[2];
                        }

                        $s = S_FIRST;
                        $useToken = PARSER_USE;
                    }
                    else
                    {
                        $function .= $this->ruleFunctionBlock($t, $convertSelf, $convertThis);
                        $useToken = PARSER_LEAVE;
                    }
                    break;

                case '}':
                    if ($s == S_FIRST)
                    {
                        $function .= $t[2];
                        $s = S_END;
                        $useToken = PARSER_USE;
                    }
                    break;

                case T_COMMENT:
                    $useToken = PARSER_USE;
                    break;
                case T_WHITESPACE:
                    $function .= $t[2];
                    $useToken = PARSER_USE;
                    break;

                default:
                    $s = S_FIRST;
                    $function .= $this->ruleExpression($t, false, $convertSelf,$convertThis);
                    $useToken = PARSER_LEAVE;
            }

            if ($useToken == PARSER_USE)
                $t = $this->tokens->moveNext();
            else if ($useToken == PARSER_LEAVE)
                $t = $this->tokens->current();
            else
                $this->syntaxError($t, "Unexpected character in function block ".(($methodInfo)?("in method '".$methodInfo['name']."'"):(""))." in state $s: ",PARSE_ERR_UNEX_CHAR);
        }
        while( $s < S_END && $t);

        return $function;
    }

    // Parse method definitions for classes, protocols, categories
    private function ruleMethodDeclaration($structureType, $firstToken, $name=false )
    {
        $labelparamList = array();
        $returnType = false;

        $paramType = false;
        $methodType = false;

        $method = false;

        $s = S_START;

        $t = $firstToken;
        do
        {
            $useToken = PARSER_ERROR;

            switch ($t[0])
            {
                case T_COMMENT:
                case T_WHITESPACE:
                    $useToken = PARSER_USE;
                    break;

                case '+':
                    if ($s == S_START)
                    {
                        $s = S_FIRST;
                        $methodType = 'c';
                        $useToken = PARSER_USE;
                    }
                    break;
                case '-':
                    if ($s == S_START)
                    {
                        $s = S_FIRST;
                        $methodType = 'i';
                        $useToken = PARSER_USE;
                    }
                    break;

                case ':':
                    if ($s == 3 || $s == 5)
                    {
                        $s = 4;
                        $useToken = PARSER_USE;
                    }
                    break;

                case '{':
                    if ($structureType != 'protocol' && ($s == 3 || $s == 5))
                    {
                        if (empty($labelparamList))
                            $this->syntaxError($t, "No Method Name specified in $structureType '$name' decleration: ",PARSE_ERR_IMP_NOMETHODNAME);

                        $s = S_END;
                        $method = $this->reflectionCreateMethodInfo($methodType, $labelparamList);
                        $useToken = PARSER_LEAVE;
                    }
                    break;

                case ';':
                    if ($structureType == 'protocol' && ($s == 3 || $s == 5))
                    {
                        if (empty($labelparamList))
                            $this->syntaxError($t, "No Method Name specified in $structureType '$name' decleration: ",PARSE_ERR_IMP_NOMETHODNAME);

                        $s = S_END;
                        $method = $this->reflectionCreateMethodInfo($methodType, $labelparamList);
                        $useToken = PARSER_USE;
                    }
                    break;

                default:
                    if ($s == S_FIRST &&
                        ($this->terminalIsPHPCastKeyword($t) || $t[0] == '('))
                    {
                        $s = 2;
                        $returnType = $this->ruleBracketedType($t);
                        $useToken = PARSER_LEAVE;
                    }
                    else if ($s == 4 &&
                        ($this->terminalIsPHPCastKeyword($t) || $t[0] == '('))
                    {
                        $s = 6; // THIS IS THE STATE FOR WHEN WE HAVE A TYPE
                        // FIXME: hint must be stored with introspection info
                        $paramType = $this->ruleBracketedType($t);
                        $useToken = PARSER_LEAVE;
                    }
                    else if (($s == S_FIRST || $s == 2) &&
                        ($this->terminalIsPHPKeyword($t) || $t[0] == T_STRING))
                    {
                        // 1st Label (method Name) with php keyword name
                        // structure name(:(type)param) THEN name:(type)param OR :(type)param)
                        $labelparamList[] = array('type'=>'l', 'value'=>$this->ruleMethodSignatureLabel($t));
                        $s = 3;
                        $useToken = PARSER_LEAVE;
                    }
                    else if ($s == 5 && array_shift(end($labelparamList)) == 'p' &&
                        ($this->terminalIsPHPKeyword($t) || $t[0] == T_STRING))
                    {
                        // A parameter label
                        // structure name(:(type)param) THEN name:(type)param OR :(type)param)
                        $labelparamList[] = array('type'=>'l', 'value'=>$this->ruleMethodSignatureLabel($t));
                        $s = 5;
                        $useToken = PARSER_LEAVE;
                    }
                    else if (($s == 4 || $s == 6) &&
                        ($this->terminalIsPHPKeyword($t) || $t[0] == T_STRING))
                    {
                        // A parameter var name
                        // structure name(:(type)param) THEN name:(type)param OR :(type)param)
                        $labelparamList[] = array('type'=>'p', 'value'=>$this->ruleMethodSignatureParameter($t), 'hint'=>$paramType);
                        $paramType = false;
                        $s = 5;
                        $useToken = PARSER_LEAVE;
                    }
                    break;
            }

            if ($useToken == PARSER_USE)
                $t = $this->tokens->moveNext();
            else if ($useToken == PARSER_LEAVE)
                $t = $this->tokens->current();
            else
                $this->syntaxError($t, "Unexpected character in ".(($method['type']=='c')?("class"):("instance"))." method in $structureType '$name' in state $s: ",PARSE_ERR_UNEX_CHAR);
        }
        while( $s < S_END && $t);

        if ($s < S_END && !$t)
            $this->syntaxError($firstToken, "Unexpected end of file while parsing ".(($method['type']=='c')?("class"):("instance"))." method in $structureType '$name': ",PARSE_ERR_UNEX_EOF);

        return $method;
    }

    // Parse a label in a method signature
    private function ruleMethodSignatureLabel($firstToken)
    {
        $s = S_START;

        $label = false;

        $t = $firstToken;
        do
        {
            $useToken = PARSER_ERROR;

            switch ($t[0])
            {
                case T_COMMENT:
                    $useToken = PARSER_USE;
                    break;

                case T_WHITESPACE:
                    $s = S_END;
                    $useToken = PARSER_LEAVE;
                    break;

                case ']':
                case ':':
                case ';':
                    if ($s == S_FIRST)
                    {
                        $s = S_END;
                        $useToken = PARSER_LEAVE;
                    }
                    break;

                case T_STRING:
                    if ($s == S_START)
                    {
                        $s = S_FIRST;
                        $label .= $t[2];
                        $useToken = PARSER_USE;
                    }
                    break;
                default:
                    if (($s == S_START) && $this->terminalIsPHPKeyword($t))
                    {
                        $s = S_FIRST;
                        $label .= $t[2];
                        $useToken = PARSER_USE;
                    }

            }

            if ($useToken == PARSER_USE)
                $t = $this->tokens->moveNext();
            else if ($useToken == PARSER_LEAVE)
                $t = $this->tokens->current();
            else
                $this->syntaxError($t, "Unexpected character in method label in state $s: ",PARSE_ERR_UNEX_CHAR);
        }
        while( $s < S_END && $t);

        if ($s < S_END && !$t)
            $this->syntaxError($firstToken, "Unexpected end of file while parsing method label: ",PARSE_ERR_UNEX_EOF);

        return $label;
    }

    // Parse a parameter name in a method signature
    private function ruleMethodSignatureParameter($firstToken)
    {
        return $this->ruleMethodSignatureLabel($firstToken);
    }

    // expression
    // FIXME: The stop on whitespace and symbols needs a refactor. prob
    // to be stop on whitespace and stop on symbols seperately
    private function ruleExpression($firstToken, $stopOnWhitespaceAndClosingSymbols=false, $convertSelf=false, $convertThis=false)
    {
        $s = S_START;

        $squareBracketOpen = false;
        $code = "";

        $t = $firstToken;
        do
        {
            $useToken = PARSER_ERROR;

            switch ($t[0])
            {
                case T_OBJPHP_THIS:
                    if ($convertThis)
                        $code .= PARSER_CLASSTHISREFNAME;
                    else
                        $code .= $t[2];
                    $useToken = PARSER_USE;
                    $s = S_FIRST;
                    break;

                case T_OBJPHP_SELF:
                    if ($convertSelf)
                        $code .= PARSER_CLASSTHISREFNAME;
                    else
                        $code .= $t[2];
                    $useToken = PARSER_USE;
                    $s = S_FIRST;
                    break;

                case T_OBJPHP_SELECTOR:
                    $code .= $this->ruleSelector($t);
                    $useToken = PARSER_LEAVE;
                    $s = S_FIRST;
                    break;

                case T_OBJPHP_PROTOCOL:
                    $code .= $this->ruleProtocol($t);
                    $useToken = PARSER_LEAVE;
                    $s = S_FIRST;
                    break;

                case T_OBJPHP_NIL:
                case T_OBJPHP_OBJNIL:
                    $code .= $this->terminalNil($t);
                    $useToken = PARSER_USE;
                    $s = S_FIRST;
                    break;

                case T_OBJPHP_YES:
                case T_OBJPHP_NO:
                    $code .= $this->terminalYesNo($t);
                    $useToken = PARSER_USE;
                    $s = S_FIRST;
                    break;

                case '{':
                    $code .= $this->ruleFunctionBlock($t,$convertSelf, $convertThis);
                    $useToken = PARSER_LEAVE;
                    $s = S_FIRST;
                    break;

                case '(':
                    if ($s == S_START && $stopOnWhitespaceAndClosingSymbols == false)
                    {
                        $code .= $t[2];
                        $useToken = PARSER_USE;
                        $s = S_FIRST;
                    }
                    else
                    {
                        $code .= $this->ruleExpression($t, false, $convertSelf, $convertThis);
                        $useToken = PARSER_LEAVE;
                        $s = S_FIRST;
                    }
                    break;

                case ')':
                    $code .= $t[2];
                    $s = S_END;
                    $useToken = PARSER_USE;
                    break;

                case '[':
                    $p = $this->tokens->previousToken();
                    $p1 = $this->tokens->previousTokenAt(1);

                    if ( ($p[0] == T_WHITESPACE && $p1[0] == T_VARIABLE) || ($p[0] == T_VARIABLE) ||
                         ($p[0] == T_WHITESPACE && $p1[0] == T_STRING) || ($p[0] == T_STRING) ||
                         ($p[0] == T_WHITESPACE && $p1[0] == ']') || ($p[0] == ']')
                        )
                    {
                        $code .= $t[2];
                        $squareBracketOpen = true;
                        $useToken = PARSER_USE;
                    }
                    else
                    {
                        $code .= $this->ruleBracketSyntax($t,$convertSelf,$convertThis);
                        $useToken = PARSER_LEAVE;
                    }

                    $s = S_FIRST;
                    break;

                case '}':  // FIXME: CHECK THE LOGIC HERE
                case ';':
                    if ($stopOnWhitespaceAndClosingSymbols)
                    {
                        $s = S_END;
                        $useToken = PARSER_LEAVE;
                    }
                    else
                    {
                        $code .= $t[2];
                        $s = S_END;
                        $useToken = PARSER_USE;
                    }
                    break;

                case '>':
                case ',':
                case ':':
                case ']':
                    if ($stopOnWhitespaceAndClosingSymbols && !$squareBracketOpen)
                    {
                        $s = S_END;
                        $useToken = PARSER_LEAVE;
                        break;
                    }
                    $squareBracketOpen = false;
                    $s = S_FIRST;
                    $code .= $t[2];
                    $useToken = PARSER_USE;
                    break;

                case T_WHITESPACE:
                    if ($stopOnWhitespaceAndClosingSymbols || (preg_match('/^[^\n\s]*\n/', $t[2]) != 0))
                    {
                        $s = S_END;
                        $useToken = PARSER_LEAVE;
                        break;
                    }
                    $code .= $t[2];
                    $useToken = PARSER_USE;
                    break;

                case T_COMMENT:
                    if (strpos($t[2], "\n") !== false)
                    {
                        $s = S_END;
                        $useToken = PARSER_LEAVE;
                        break;

                    }
                    $useToken = PARSER_USE;
                    break;

                case T_OPEN_TAG:
                case T_CLOSE_TAG:
                    if (!$stopOnWhitespaceAndClosingSymbols)
                    {
                        $s = S_END;
                        $code .= $t[2];
                        $useToken = PARSER_USE;
                    }
                    break;

                default:
                    $s = S_FIRST;
                    $code .= $t[2];
                    $useToken = PARSER_USE;
                    break;
            }

            if ($useToken == PARSER_USE)
                $t = $this->tokens->moveNext();
            else if ($useToken == PARSER_LEAVE)
                $t = $this->tokens->current();
            else
                $this->syntaxError($t, "Unexpected character in expression in state $s: ",PARSE_ERR_UNEX_CHAR);

        }
        while( $s < S_END && $t);

        return $code;
    }

    // parse message passing syntax '[]'
    private function ruleBracketSyntax($firstToken, $convertSelf = false, $convertThis = false)
    {
        $s = S_START;

        $receiver = false;
        $labelParamsList = array();
        $paramType = false;

        $t = $firstToken;
        do
        {
            $useToken = PARSER_ERROR;

            switch ($t[0])
            {
                case T_WHITESPACE:
                case T_COMMENT:
                    $nt = $this->tokens->peekToken();
                    if ($s == 10 && $nt[0] != T_DOUBLE_COLON)
                    {
                        $pt = $this->tokens->previousToken();
                        if ($pt[2] != 'super' && !$this->reflectionClassExists($pt[2]))
                            $this->syntaxError($firstToken, "Receiver class (".$pt[2].") does not exist or has not yet been encountered: ",PARSE_ERR_UNDEF_RECEIVER);
                        $receiver = array('type'=>'s','value'=>$pt[2]);
                        $s = 2;
                    }
                    $useToken = PARSER_USE;
                    break;

                // receiver or pass to expression parser
                case '[':
                    if ($s == S_START)
                    {
                        $s = S_FIRST;
                        $useToken = PARSER_USE;
                    }
                    else if ($s == S_FIRST)
                    {
                        $receiver = array('type'=>'e', 'value'=>$this->ruleExpression($t, true,$convertSelf,$convertThis));
                        $s = 2;
                        $useToken = PARSER_LEAVE;
                    }
                    else if ($s == 4)
                    {
                        $labelParamsList[] = array('type'=>'p', 'value'=>$this->ruleExpression($t, true, $convertSelf, $convertThis));
                        $s = 5;
                        $useToken = PARSER_LEAVE;
                    }
                    break;

                case ':':
                    if ($s == 3 || $s == 5)
                    {
                        $s = 4;
                        $useToken = PARSER_USE;
                    }
                    else if ($s == 2 && count($labelParamsList))
                    {
                        $s = 4;
                        $useToken = PARSER_USE;
                    }
                    break;

                case ']':
                    if (($s == 3 && count($labelParamsList) == 1) || ($s == 5 && count($labelParamsList)))
                    {
                        $s = S_END;
                        $useToken = PARSER_USE;
                    }
                    break;

                default:
                    if ($s == S_FIRST && ($t[0] == T_VARIABLE
                                       || $t[0] == T_OBJPHP_PROTOCOL
                                       || $t[0] == T_OBJPHP_SELF
                                       || $t[0] == T_OBJPHP_THIS))
                    {
                        $receiver = array('type'=>'e','value'=>$this->ruleExpression($t, true,$convertSelf,$convertThis));
                        $s = 2;
                        $useToken = PARSER_LEAVE;
                    }
                    else if ($s == 10)
                    {
                        // string plus the rest of the expression
                        $receiver = array('type'=>'e','value'=>$receiver['value'].$this->ruleExpression($t, true,$convertSelf,$convertThis));
                        $s = 2;
                        $useToken = PARSER_LEAVE;
                    }
                    /*
                     * IF it is a STRIng then we consider it a ObjPHP object name
                     * if it has anymore things after then its considered as for
                     * example a PHP class and ::func() (which ISNT valid objphp)
                     */
                    else if ($s == S_FIRST && $t[0] == T_STRING)
                    {
                        $s = 10;
                        $useToken = PARSER_USE;
                    }
                    else if (($s == S_FIRST || $s == 2) &&
                        ($this->terminalIsPHPKeyword($t) || $t[0] == T_STRING))
                    {
                        // 1st Label (method Name) with php keyword name
                        $labelParamsList[] = array('type'=>'l', 'value'=>$this->ruleMethodSignatureLabel($t));
                        $s = 3;
                        $useToken = PARSER_LEAVE;
                    }
                    else if ($s == 5 && array_shift(end($labelParamsList)) == 'p' &&
                        ($this->terminalIsPHPKeyword($t) || $t[0] == T_STRING))
                    {
                        // A parameter label
                        $labelParamsList[] = array('type'=>'l', 'value'=>$this->ruleMethodSignatureLabel($t));
                        $s = 5;
                        $useToken = PARSER_LEAVE;
                    }
                    else if ($s == 4)
                    {
                        // A parameter from expression
                        $labelParamsList[] = array('type'=>'p', 'value'=>$this->ruleExpression($t, true,$convertSelf,$convertThis));
                        $s = 5;
                        $useToken = PARSER_LEAVE;
                    }
                    break;
            }
            if ($useToken == PARSER_USE)
                $t = $this->tokens->moveNext();
            else if ($useToken == PARSER_LEAVE)
                $t = $this->tokens->current();
            else
                $this->syntaxError($t, "Unexpected character in square bracket syntax in state $s: ",PARSE_ERR_UNEX_CHAR);
        }
        while( $s < S_END && $t);

        if ($s < S_END && !$t)
            $this->syntaxError($startToken, "Unexpected end of file while parsing square bracket syntax: ",PARSE_ERR_UNEX_EOF);

        return $this->generateMethodCall($receiver, $labelParamsList);
    }

    // @import
    private function ruleImport($firstToken, $convertSelf = false, $convertThis = false)
    {
        // import involves reading in file, tokenizing, and adding to token chain at current point
        $s = S_START;

        $source = true;

        $fileName = "";
        $dynamicImport = false;
        $importType = true; // true relative, false include paths

        $t = $firstToken;
        do
        {
            $useToken = PARSER_ERROR;

            switch ($t[0])
            {
                case T_COMMENT:
                case T_WHITESPACE:
                    $useToken = PARSER_USE;
                    break;

                case T_OBJPHP_IMPORT:
                    if ($s == S_START)
                    {
                        $s = S_FIRST;
                        $useToken = PARSER_USE;
                    }
                    break;

                case '<':
                    if ($s == S_FIRST)
                    {
                        $s = 2;
                        $useToken = PARSER_USE;
                    }
                    break;

                case '>':
                    if ($s == 3)
                    {
                        $s = S_END;
                        $importType = false;
                        $useToken = PARSER_USE;
                    }
                    break;

                // for "file path"
                case T_CONSTANT_ENCAPSED_STRING:
                    if ($s == S_FIRST)
                    {
                        $fileName .= str_replace(array("\"","'"),"",$t[2]);
                        $s = S_END;
                        $useToken = PARSER_USE;
                    }
                    break;

                // for <file path>
                case '.': case '/': case '\\':
                case T_STRING:
                    if ($s == 2)
                    {
                        $fileName .= $t[2];
                        $s = 3;
                        $useToken = PARSER_USE;
                    } elseif ($s == 3)
                    {
                        $fileName .= $t[2];
                        $useToken = PARSER_USE;
                    }
                default:
                    // Dynamic imports are ALWAYS full (not relative to include paths) (is no < >)
                    if ($s == S_FIRST)
                    {
                        $fileName .= $this->ruleExpression($t, true, $convertSelf, $convertThis);
                        $dynamicImport = true;
                        $s = S_END;
                        $useToken = PARSER_LEAVE;
                    }
                    else /*if ($s == 2)
                    {
                        $fileName .= $this->ruleExpression($t, true, $convertSelf, $convertThis);
                        $dynamicImport = true;
                        $s = 3;
                        $useToken = PARSER_LEAVE;
                    }*/
                    break;
            }

            if ($useToken == PARSER_USE)
                $t = $this->tokens->moveNext();
            else if ($useToken == PARSER_LEAVE)
                $t = $this->tokens->current();
            else
                $this->syntaxError($t, "Unexpected character in @import in state $s: ",PARSE_ERR_UNEX_CHAR);
        }
        while( $s < S_END && $t);

        if ($s < S_END && !$t)
            $this->syntaxError($firstToken, "Unexpected end of file while parsing @import: ",PARSE_ERR_UNEX_EOF);

        if (!$dynamicImport)
        {
            // with filename we can read file n addtokens then continue parsing
            $import = $this->readImport($fileName, $importType);
            if ($import)
                $this->tokens->addTokens($import);
        }
        else
        {
            // else we are going to dynamically load so generate the source
            // $this in the preprocessor should be the preprocessor
            $source = $this->generateDynamicImport($fileName);
        }

        return $source;
    }

    // @php
    private function rulePHPBlock($firstToken)
    {
        $s = S_START;

        $php = "";

        $t = $firstToken;
        do
        {
            $useToken = PARSER_ERROR;

            switch ($t[0])
            {
                case T_COMMENT:
                    $useToken = PARSER_USE;
                    break;

                case T_OBJPHP_PHP:
                    if ($s == S_START)
                    {
                        $s = S_FIRST;
                        $useToken = PARSER_USE;
                    }
                    break;

                case T_OBJPHP_END:
                    if ($s == S_FIRST)
                    {
                        $s = S_END;
                        $useToken = PARSER_USE;
                    }
                    break;

                default:
                    $php .= $t[2];
                    $useToken = PARSER_USE;
            }

            if ($useToken == PARSER_USE)
                $t = $this->tokens->moveNext();
            else if ($useToken == PARSER_LEAVE)
                $t = $this->tokens->current();
            else
                $this->syntaxError($t, "Unexpected character in @php in state $s: ",PARSE_ERR_UNEX_CHAR);
        }
        while( $s < S_END && $t);

        if ($s < S_END && !$t)
            $this->syntaxError($firstToken, "Unexpected end of file while parsing @php: ",PARSE_ERR_UNEX_EOF);

        return $php;
    }

    // @selector
    private function ruleSelector($firstToken)
    {
        $s = S_START;

        $methodName = "m_";

        $t = $firstToken;
        do
        {
            $useToken = PARSER_ERROR;

            switch ($t[0])
            {
                case T_COMMENT:
                case T_WHITESPACE:
                    $useToken = PARSER_USE;
                    break;

                case T_OBJPHP_SELECTOR:
                    if ($s == S_START)
                    {
                        $s = S_FIRST;
                        $useToken = PARSER_USE;
                    }
                    break;

                case '(':
                    if ($s == S_FIRST)
                    {
                        $s = 2;
                        $useToken = PARSER_USE;
                    }
                    break;

                case T_STRING:
                //case T_ENCAPSED_AND_WHITESPACE:
                //case T_CONSTANT_ENCAPSED_STRING:
                    if ($s == 2 || $s == 3)
                    {
                        $s = 3;
                        $methodName .= $t[2];
                        $useToken = PARSER_USE;
                    }
                    break;

                case ':':
                    if ($s == 3)
                    {
                        $methodName .= '_';
                        $useToken = PARSER_USE;
                    }
                    break;

                case T_DOUBLE_COLON:
                    if ($s == 3)
                    {
                        $methodName .= '__';
                        $useToken = PARSER_USE;
                    }
                    break;

                case ')':
                    if ($s == 3)
                    {
                        $s = S_END;
                        $useToken = PARSER_USE;
                    }
                    break;

            }
            if ($useToken == PARSER_USE)
                $t = $this->tokens->moveNext();
            else if ($useToken == PARSER_LEAVE)
                $t = $this->tokens->current();
            else
                $this->syntaxError($t, "Unexpected character in @selector in state $s: ",PARSE_ERR_UNEX_CHAR);
        }
        while( $s < S_END && $t);

        if ($s < S_END && !$t)
            $this->syntaxError($startToken, "Unexpected end of file while parsing @selector: ",PARSE_ERR_UNEX_EOF);

        return "'".$methodName."'";
    }

    // by default (,) is assumed. Start and End are Tokens (e.g. T_DOUBLE_COLON or ':') but NOT T_WHITESPACE
    private function ruleCommaSeparatedList($firstToken, $startToken='(', $endToken=')', $expressions=false)
    {
        $s = S_START;

        $source = false;
        $list = array();

        $t = $firstToken;
        do
        {
            $useToken = PARSER_ERROR;

            switch ($t[0])
            {
                case T_WHITESPACE:
                case T_COMMENT:
                    $useToken = PARSER_USE;
                    break;

                case $startToken:
                    if ($s == S_START)
                    {
                        $s = S_FIRST;
                        $useToken = PARSER_USE;
                    }
                    break;

                case ',':
                    if ($s == 2)
                    {
                        $s = S_FIRST;
                        $list[] = $source;
                        $source = "";
                        $useToken = PARSER_USE;
                    }
                    break;

                case $endToken:
                    $s = S_END;
                    $list[] = $source;
                    $source = "";
                    $useToken = PARSER_LEAVE;
                    break;

                case T_STRING:
                    if ($s == S_FIRST && !$expressions)
                    {
                        $s = 2;
                        $source .= $t[2];
                        $useToken = PARSER_USE;
                    }

                default:
                    if ($s == S_FIRST && $expressions)
                    {
                        $s = 2;
                        $source .= $this->ruleExpression($t, true);
                        $useToken = PARSER_LEAVE;
                    }
                    break;
            }

            if ($useToken == PARSER_USE)
                $t = $this->tokens->moveNext();
            else if ($useToken == PARSER_LEAVE)
                $t = $this->tokens->current();
            else
                $this->syntaxError($t, "Unexpected character in comma separated list in state $s: ",PARSE_ERR_UNEX_CHAR);
        }
        while( $s < S_END && $t);

        if ($s < S_END && !$t)
            $this->syntaxError($firstToken, "Unexpected end of file while parsing in comma separated list: ",PARSE_ERR_UNEX_EOF);

        return $list;
    }

    // stop on )
    private function ruleBracketedType($firstToken)
    {
        $s = S_START;

        $type = false;

        $t = $firstToken;
        do
        {
            $useToken = PARSER_ERROR;

            switch ($t[0])
            {
                case T_WHITESPACE:
                case T_COMMENT:
                    $useToken = PARSER_USE;
                    break;

                // casts consume the brackets
                case T_DOUBLE_CAST:
                    if ($s == S_START)
                    {
                        $s = S_END;
                        $type .= "float";
                        $useToken = PARSER_USE;
                    }
                    break;
                case T_INT_CAST:
                    if ($s == S_START)
                    {
                        $s = S_END;
                        $type .= "int";
                        $useToken = PARSER_USE;
                    }
                    break;
                case T_OBJECT_CAST:
                    if ($s == S_START)
                    {
                        $s = S_END;
                        $type .= "object"; // id ?
                        $useToken = PARSER_USE;
                    }
                    break;
                case T_STRING_CAST:
                    if ($s == S_START)
                    {
                        $s = S_END;
                        $type .= "string";
                        $useToken = PARSER_USE;
                    }
                    break;
                case T_BOOL_CAST:
                    if ($s == S_START)
                    {
                        $s = S_END;
                        $type .= "boolean";
                        $useToken = PARSER_USE;
                    }
                    break;
                case T_ARRAY_CAST:
                    if ($s == S_START)
                    {
                        $s = S_END;
                        $type .= "array";
                        $useToken = PARSER_USE;
                    }
                    break;

                // otherwise expect brackets
                case '(':
                    if ($s == S_START)
                    {
                        $s = S_FIRST;
                        $useToken = PARSER_USE;
                    }
                    break;

                case ')':
                    if ($s == 2)
                    {
                        $s = S_END;
                        $useToken = PARSER_USE;
                    }
                    break;

                default:
                    if ($s == S_FIRST || $s == 2)
                    {
                        $type .= $t[2];
                        $s = 2;
                        $useToken = PARSER_USE;
                    }
                    break;
            }

            if ($useToken == PARSER_USE)
                $t = $this->tokens->moveNext();
            else if ($useToken == PARSER_LEAVE)
                $t = $this->tokens->current();
            else
                $this->syntaxError($t, "Unexpected character in ".(($methodType=='c')?("class"):("instance"))." method in state $s: ",PARSE_ERR_UNEX_CHAR);
        }
        while( $s < S_END && $t);

        if ($s < S_END && !$t)
            $this->syntaxError($firstToken, "Unexpected end of file while parsing ".(($methodType=='c')?("class"):("instance"))." method: ",PARSE_ERR_UNEX_EOF);

        return $type;
    }

    // YES & NO
    private function terminalYesNo($startToken)
    {
        return ($startToken[0] == T_OBJPHP_YES)?("true"):("false");
    }

    // nil and Nil
    private function terminalNil($startToken)
    {
        return "null";
    }

    private function terminalIsPHPCastKeyword($t)
    {
        $token = $t[0];
        if ( $token == T_DOUBLE_CAST
            || $token == T_INT_CAST
            || $token == T_OBJECT_CAST
            || $token == T_STRING_CAST
            || $token == T_ARRAY_CAST
            || $token == T_BOOL_CAST )
            return true;
        else
            return false;
    }

    private function terminalIsPHPKeyword($t)
    {
        $token = $t[0];
        if ( $token == T_ABSTRACT
            || $token == T_ARRAY
            || $token == T_AS
            || $token == T_BREAK
            || $token == T_CASE
            || $token == T_CATCH
            || $token == T_CLASS
            || $token == T_CLONE
            || $token == T_CONST
            || $token == T_CONTINUE
            || $token == T_DECLARE
            || $token == T_DEFAULT
            || $token == T_DO
            || $token == T_ECHO
            || $token == T_ELSE
            || $token == T_ELSEIF
            || $token == T_EMPTY
            || $token == T_ENDDECLARE
            || $token == T_ENDFOR
            || $token == T_ENDFOREACH
            || $token == T_ENDIF
            || $token == T_ENDSWITCH
            || $token == T_ENDWHILE
            || $token == T_EXIT
            || $token == T_EXTENDS
            || $token == T_FINAL
            || $token == T_FOR
            || $token == T_FOREACH
            || $token == T_FUNCTION
            || $token == T_GLOBAL
            || $token == T_GOTO
            || $token == T_IF
            || $token == T_IMPLEMENTS
            || $token == T_INCLUDE
            || $token == T_INCLUDE_ONCE
            || $token == T_INSTANCEOF
            || $token == T_INTERFACE
            || $token == T_ISSET
            || $token == T_LIST
            || $token == T_LOGICAL_AND
            || $token == T_LOGICAL_OR
            || $token == T_LOGICAL_XOR
            || $token == T_NEW
            || $token == T_PRINT
            || $token == T_PRIVATE
            || $token == T_PUBLIC
            || $token == T_PROTECTED
            || $token == T_REQUIRE
            || $token == T_REQUIRE_ONCE
            || $token == T_RETURN
            || $token == T_STATIC
            || $token == T_SWITCH
            || $token == T_THROW
            || $token == T_TRY
            || $token == T_UNSET
            || $token == T_USE
            || $token == T_VAR
            || $token == T_WHILE )
            return true;
        else
            return false;
    }

    private function ruleTemplate($firstToken)
    {
        $s = S_START;
        $t = $firstToken;

        $source = false;

        do
        {
            $useToken = PARSER_ERROR;

            switch ($t[0])
            {
                case T_COMMENT:
                    $useToken = PARSER_USE;
                    break;

                // these consume the brackets
                case T_DOUBLE_CAST:
                case T_INT_CAST:
                case T_OBJECT_CAST:
                case T_STRING_CAST:
                case T_BOOL_CAST:
                    break;

                // otherwise expect brackets
                case '(':
                    break;

                case ')':
                    break;

                default:
                    break;
            }

            if ($useToken == PARSER_USE)
                $t = $this->tokens->moveNext();
            else if ($useToken == PARSER_LEAVE)
                $t = $this->tokens->current();
            else
                $this->syntaxError($t, "Unexpected character in ".(($methodType=='c')?("class"):("instance"))." method in state $s: ",PARSE_ERR_UNEX_CHAR);
        }
        while($s < S_END && $t);

        if ($s < S_END && !$t)
            $this->syntaxError($firstToken, "Unexpected end of file while parsing ".(($methodType=='c')?("class"):("instance"))." method: ",PARSE_ERR_UNEX_EOF);

        return $source;
    }

    // *********************
    private function checkProtocolConformance($className, $protocolName, $catName = false)
    {
        // for both $protocolName and any inherited protocols check className or
        // any class higher in hierarchy implements the given methods

        // Issue warning or syntaxError if not obeyed
        if ($p = $this->reflectionProtocolProtocols($protocolName))
        {
            foreach($p as $inheritedProtocol)
            {
                $this->checkProtocolConformance($className, $inheritedProtocol);
            }
        }

        // check us
        if ($methods = $this->reflectionProtocolInstanceMethods($protocolName))
        {
            foreach($methods as $pMethodName => $pMethod)
            {
                $c = $className;
                while ($c)
                {
                    if ($this->reflectionClassInstanceMethodExists($className, $pMethodName))
                        return true;
                    $c = $this->reflectionClassParent($c);
                }
                $this->syntaxError(null, (($catName)?("Category '$catName' of "):(""))."Class '$className' does not obey protocol '$protocolName'. Missing instance method with signature '".\ObjPHP\selectorFromMethodName($pMethodName)."'",PARSE_ERR_PROTOCOL_CONFORMANCE);
                return false;
            }
        }
        if ($methods = $this->reflectionProtocolClassMethods($protocolName))
        {
            foreach($methods as $pMethod)
            {
                $c = $className;
                while ($c)
                {
                    if ($this->reflectionClassClassMethodExists($className, $pMethod['name']))
                        break 2;
                    $c = $this->reflectionClassParent($c);
                }
                $this->syntaxError(null, (($catName)?("Category '$catName' of "):(""))."Class '$className' does not obey protocol '$protocolName'. Missing instance method with signature '".\ObjPHP\selectorFromMethodName($pmethod['name'])."'",PARSE_ERR_PROTOCOL_CONFORMANCE);
                return false;
            }
        }

        return true;
    }

    // *********************
    private function generateGetAccessor($className, $iVarName)
    {
        $label[0] = array('type'=>'l', 'value'=>$iVarName);
        $methodInfo = $this->reflectionCreateMethodInfo('i', $label);
        $this->reflectionClassAddMethod($className, $methodInfo, "return ".PARSER_CLASSTHISREFNAME."->$iVarName;\n}", _METHOD_CLASS);
    }

    private function generateSetAccessor($className, $iVarName)
    {

        $label[0] = array('type'=>'l', 'value'=>'set'.ucfirst($iVarName));
        $label[1] = array('type'=>'p', 'value'=>'obj');
        $methodInfo = $this->reflectionCreateMethodInfo('i', $label);
        $this->reflectionClassAddMethod($className, $methodInfo, PARSER_CLASSTHISREFNAME."->$iVarName = \$obj;\n}", _METHOD_CLASS);
    }

    private function generateDynamicImport($fileName)
    {
        // below tokenizes and adds tokens to end of chain, then parses it
        $source = "global \$_objphp_preprocessor; \$_objphp_importSource = \$_objphp_preprocessor->loadObjPHPFileWithoutReset($fileName, true, true);\n";
        $source .= "if (\$_objphp_importSource === false) throw new \ObjPHP\ParseException(null, 'Parser error in runtime import.', ".RUNTIME_IMPORTPARSER_ERROR.");";
        $source .= "if (\$_objphp_preprocessor->run(\$_objphp_importSource, \$_op_obj) === false) throw new \ObjPHP\RuntimeException('Runtime error in import at runtime.', ".RUNTIME_ERROR.");";
        return $source;
    }

    private function generateClassSource($name, $parentName)
    {
        return $this->generateObjectSource('class', $name, $parentName);
    }

    private function generateProtocolSource($name)
    {
        return $this->generateObjectSource('protocol', $name, PARSER_ROOTPROTOCOLOBJECT_NAME);
    }

    private function generateObjectSource($type, $name, $parentName)
    {
        $metaSource = "";
        $classSource = "";
        $instSource = "";

        switch ($type)
        {
            case 'class':
                $root = PARSER_BASECLASS;
                $runtimeroot = PARSER_BASERUNTIMECLASS;
                $rootobject = PARSER_ROOTOBJECT_NAME;
                $instprefix  = PARSER_INSTCLASS_PREFIX;
                $metaprefix  = PARSER_METACLASS_PREFIX;
                $classprefix = PARSER_CLASSCLASS_PREFIX;
                $parentmetaprefix = PARSER_METACLASS_PREFIX;
                $parentclassprefix = PARSER_CLASSCLASS_PREFIX;
                $rootmetaprefix = $metaprefix;
                $nameprefix = '';
                $testconformance = true;
                break;

            case 'protocol':
                $root = PARSER_BASEPROTOCOLCLASS;
                $runtimeroot = PARSER_BASERUNTIMEPROTOCOLCLASS;
                $rootobject = PARSER_ROOTPROTOCOLOBJECT_NAME;
                $instprefix  = PARSER_PROTOCOLINST_PREFIX;
                $metaprefix  = PARSER_PROTOCOLMETA_PREFIX;
                $classprefix = PARSER_PROTOCOLCLASS_PREFIX;
                $parentmetaprefix = PARSER_METACLASS_PREFIX;
                $parentclassprefix = PARSER_CLASSCLASS_PREFIX;
                $rootmetaprefix = $parentmetaprefix;
                $nameprefix = PARSER_PROTOCOLINST_PREFIX;
                $testconformance = false;
                break;
        }

        // Ivar inheritance
        $parent = $this->reflectionClassParent($nameprefix.$name);
        while ($parent)
        {
            if($p = $this->reflectionClassProperties($parent))
                foreach($p as $pName => $property)
                    if (!array_key_exists($pName, $this->reflectionClassProperties($nameprefix.$name)) && strtolower($property['vis']) != 'private')
                        $this->reflectionClassAddPropertyWithProperty($nameprefix.$name, $p);

            $parent = $this->reflectionClassParent($parent);
        }

        // FIXME: sort by vis - output ivars
        // In ObjC visibility is purely a compile time thing.
        // As should be here!
        if($p = $this->reflectionClassProperties($nameprefix.$name))
            foreach($p as  $iVarName => $iVar)
                $instSource .= (($iVar['vis'])?("/* ".$iVar['vis']." */"):("")).
                    "var \$".$iVarName.
                    (($iVar['value'])?(" = ".$iVar['value']):("")).";"
                    .(($iVar['type'])?("// ".$iVar['type']."\n"):("\n"));

        // add +name automatically
        $label[0] = array('type'=>'l', 'value'=>'name');
        $methodInfo = $this->reflectionCreateMethodInfo('c', $label);
        $this->reflectionClassAddMethod($nameprefix.$name, $methodInfo, "\$this->name = \"$name\"; return \$this->name;\n}", _METHOD_CLASS);

        // add Protocols
        // check if protocols are obeyed?
        // FIXME: in OBjC only a warning is produced at compile time.
        $protocolListSource = "";
        if ($protocols = $this->reflectionClassProtocols($nameprefix.$name)) //
        {
            foreach($protocols as $p)
            {
                //if (!$this->checkProtocolConformance($className, $p))
                //    $this->syntaxError($startTokent, "Class '$className' does not obey protocol '$p' in state $s: ",PARSE_ERR_UNEX_CHAR);
                if ($testconformance)
                    $this->checkProtocolConformance($nameprefix.$name, $p);
                $protocolListSource .= "self::\$_instance->addProtocol(".PARSER_PROTOCOLCLASS_PREFIX."$p::getInstance());\n";
            }
        }
        if ($protocolListSource[strlen($protocolListSource)-1] == ",")
            $protocolListSource[strlen($protocolListSource)-1] = " ";

        // Add methods to dispatch table
        $classMethodDTable = "";
        if ($methods = $this->reflectionClassClassMethods($nameprefix.$name))
            foreach ($methods as $mName => $m)
                $classMethodDTable .= "self::\$_instance->dispatchTable['$mName']['dispatchmethod'] = _METHOD_CLASS;\n";
        $instMethodDTable = "";
        if ($methods = $this->reflectionClassInstanceMethods($nameprefix.$name))
            foreach ($methods as $mName => $m)
                $instMethodDTable .= "self::\$_instance->dispatchTable['$mName']['dispatchmethod'] = _METHOD_CLASS;\n";

        // create getInstance methods
        $getClassInstance = "public static function getInstance() {
    if (!(self::\$_instance instanceof self))
    {
        self::\$_instance = new self();
        self::\$_instance->info = _CLS_CLASS;
        self::\$_instance->isa = ".$metaprefix.$name."::getInstance();
        ".(($parentName)?("self::\$_instance->super_class = ".$parentclassprefix.$parentName."::getInstance();"):("self::\$_instance->super_class = null;"))."
            $protocolListSource
            $instMethodDTable
        self::\$_instance->setUID();
        \ObjPHP\objphp_msgSend(self::\$_instance, \"m_initialize\", array());
        self::\$_instance->name = \ObjPHP\objphp_msgSend(self::\$_instance, \"m_name\", array());
    }
    return self::\$_instance;
}\n";

        $getMetaInstance = "public static function getInstance() {
    if (!(self::\$_instance instanceof self))
    {
        self::\$_instance = new self();
        self::\$_instance->info = _CLS_META;
        self::\$_instance->isa = ".(($parentName)?($rootmetaprefix.$rootobject."::getInstance();"):("null;"))."
            $classMethodDTable
            ".(($parentName)?("self::\$_instance->super_class = ".$parentmetaprefix.$parentName."::getInstance();"):("self::\$_instance->super_class = null;"))."
        self::\$_instance->setUID();
    }
    return self::\$_instance;
}\n";

        // for Protocol Instance objects which are also singletons
        $getInstInstance = "public static function getInstance() {
    if (!(self::\$_instance instanceof self))
    {
        self::\$_instance = new self();
        self::\$_instance->isa = $classprefix$name::getInstance();
        self::\$_instance->info = _CLS_PROTOCOL;
        self::\$_instance->setUID();
    }
    return self::\$_instance;
}\n";

        // Other necessary methods
        $constructor = "function __construct() { }\n";
        $factory = "public function factory()
{
    \$o = new ".$instprefix.$name."();
    \$o->isa  = \$this;
    \$o->setUID();
    return \$o;
}\n";
        $inststatic = "private static \$_instance;\n"; // stays here to prevent LSB problem

        // create methods
        if ($methods = $this->reflectionClassClassMethods($nameprefix.$name))
            foreach ($methods as $mName => $methodInfo)
            {
                $metaSource .= "public function $mName(".PARSER_CLASSTHISREFNAME.", ".PARSER_CLASSPARAMOBJNAME.")\n{";
                $metaSource .= '//'.$methodInfo['sel']."\n\$_cmd = '".$methodInfo['sel']."';\n";
                foreach ($methodInfo['params'] as $pKey => $p)
                {
                    $metaSource .= "\$".$p['value']." = ".PARSER_CLASSPARAMOBJNAME."[".$pKey."]; // ".$p['hint']."\n";
                }

                $metaSource .= $methodInfo['source']."\n";
            }
        if ($methods = $this->reflectionClassInstanceMethods($nameprefix.$name))
            foreach($methods as $mName => $methodInfo)
            {
                $classSource .= "public function $mName(".PARSER_CLASSTHISREFNAME.", ".PARSER_CLASSPARAMOBJNAME.")\n{";
                $classSource .= '//'.$methodInfo['sel']."\n\$_cmd = '".$methodInfo['sel']."';\n";
                foreach ($methodInfo['params'] as $pKey => $p)
                {
                    $classSource .= "\$".$p['value']." = ".PARSER_CLASSPARAMOBJNAME."[".$pKey."]; // ".$p['hint']."\n";
                }

                $classSource .= $methodInfo['source']."\n";
            }
        // Create object source
        $sourceMeta = "class ".$metaprefix.$name." extends ".$runtimeroot." {\nprivate ".$constructor.$inststatic.$metaSource.$getMetaInstance."\n}\n";

        switch ($type)
        {
            case 'class':
                $sourceClass = "class ".$classprefix.$name." extends ".$runtimeroot." {\nprivate ".$constructor.$factory.$inststatic.$classSource.$getClassInstance."\n}\n";
                $sourceInst = "class ".$instprefix.$name." extends ".$root." {\npublic ".$constructor.$instSource."\n}\n";
                break;

            case 'protocol':
                $sourceClass = "class ".$classprefix.$name." extends ".$runtimeroot." {\nprivate ".$constructor.$inststatic.$classSource.$getClassInstance."\n}\n";
                $sourceInst = "class ".$instprefix.$name." extends ".$root." {\nprivate ".$constructor.$inststatic.$getInstInstance.$instSource."\n}\n";
                break;
        }

        $this->reflectionClassSetPHP($nameprefix.$name, $sourceMeta, $sourceClass, $sourceInst);

        $source = $sourceMeta.$sourceClass.$sourceInst;

        return $source;
    }

    private function generateCategorySource($className, $catName)
    {
        // build source
        //$cat = $this->reflectionClassCategory($className, $catName);
        $source = "";
        if ($methods = $this->reflectionClassCategoryClassMethods($className, $catName))
        {
            foreach($methods as $mName => $m)
            {
                $source .= "\$".PARSER_CATEGORYMETHOD_PREFIX.$mName." = function(".PARSER_CLASSTHISREFNAME.",".PARSER_CLASSPARAMOBJNAME.")\n{".$m['source'].";\n";
                $source .= PARSER_METACLASS_PREFIX."$className::getInstance()->addMethodWithMethodName(\"$mName\",\$".PARSER_CATEGORYMETHOD_PREFIX.$mName.");\n";

            }
        }
        if ($methods = $this->reflectionClassCategoryInstanceMethods($className, $catName))
        {
            foreach($methods as $mName => $m)
            {
                $source .= "\$".PARSER_CATEGORYMETHOD_PREFIX.$mName." = function(".PARSER_CLASSTHISREFNAME.",".PARSER_CLASSPARAMOBJNAME.")\n{".$m['source'].";\n";
                $source .= PARSER_CLASSCLASS_PREFIX."$className::getInstance()->addMethodWithMethodName(\"$mName\",\$".PARSER_CATEGORYMETHOD_PREFIX.$mName.");\n";
            }
        }

        // check conformance??? and  add protocols
        // Conformance can only be checked vs Cat as otherwise Cat hasnt yet been
        // added to class (added at runtime). Can a Cat add protocols and not
        // define the methods in the protocols? (assuming they are already in the
        // class)
        $protocolListSource = "";
        if ($protocols = $this->reflectionClassCategoryProtocols($className, $catName))
        {
            foreach($protocols as $p)
            {
                //$this->checkProtocolConformance($className, $p, $catName);
                $protocolListSource .= PARSER_CLASSCLASS_PREFIX."$className::getInstance()->addProtocol($p::getInstance());\n";
            }
        }

        return $source.$protocolListSource;
    }

    private function generateMethodCall($receiver, $labelParamsList)
    {
        $source = "";
        $rec = false;

        $methodInfo = $this->reflectionCreateMethodInfo('call', $labelParamsList);

        if ($receiver['type'] == 'e')
        {
            $source = '\ObjPHP\objphp_msgSend';
            $rec = $receiver['value'];
        }
        else if ($receiver['type'] == 's')
        {
            if ($receiver['value'] == 'super')
            {
                $source = '\ObjPHP\objphp_msgSendSuper';
                $rec = PARSER_CLASSTHISREFNAME;
            }
            else
            {
                $source = '\ObjPHP\objphp_msgSend';
                $rec = PARSER_CLASSCLASS_PREFIX.$receiver['value']."::getInstance()";
            }
        }

        $params = array();
        foreach ($methodInfo['params'] as $p)
            $params[] = $p['value'];

        $source .= '('.$rec.',"'.$methodInfo['name'].'",array('.implode(',', $params).'))';

        return $source;
    }

    // *********************
    private function reflectionCreateMethodInfo($type, $labelparamList)
    {
        $methodDecl = "m_";
        $sel = "";

        $params = array();

        $cnt = 0;
        for($i = 0; $i < count($labelparamList); $i++)
        {
            if ($labelparamList[$i]['type'] === 'l')
            {
                $methodDecl .= $labelparamList[$i]['value'];
                $sel .= $labelparamList[$i]['value'];
            }
            else
            {
                $params[$cnt]['value'] = $labelparamList[$i]['value'];
                $params[$cnt]['hint'] = $labelparamList[$i]['hint'];
                $methodDecl .= '_';
                $sel .= ":";
                $cnt++;
            }
        }

        $method['params'] = $params;
        $method['name'] = $methodDecl;
        $method['type'] = $type;
        $method['sel'] = $sel;

        return $method;
    }

    private function reflectionClassAdd($className)
    {
        $this->classes[$className]['parent'] = false;
        $this->classes[$className]['properties'] = false;
        $this->classes[$className]['methods']['i'] = array();
        $this->classes[$className]['methods']['c'] = array();
    }

    private function reflectionClassExists($className)
    {
        return array_key_exists($className, $this->classes);
    }

    private function reflectionClassParent($className)
    {
        return $this->classes[$className]['parent'];
    }

    private function reflectionClassSetParent($className, $parentClassName)
    {
        $this->classes[$className]['parent'] = $parentClassName;
    }

    private function reflectionClassSetPHP($className, $sourceMeta, $sourceClass, $sourceInst)
    {
        $this->classes[$className]['source']['meta']  = $sourceMeta;
        $this->classes[$className]['source']['class'] = $sourceClass;
        $this->classes[$className]['source']['inst']  = $sourceInst;
    }

    private function reflectionClassAddProtocol($className, $pName)
    {
        $this->classes[$className]['protocols'][] = $pName;
    }

    private function reflectionProtocolAdd($protocolName)
    {
        $this->protocols[$protocolName]['protocols'] = array();
        $this->protocols[$protocolName]['methods']['i'] = array();
        $this->protocols[$protocolName]['methods']['c'] = array();
    }

    private function reflectionProtocolProtocols($protocolName)
    {
        return $this->protocols[$protocolName]['protocols'];
    }

    private function reflectionProtocolAddProtocol($protocolName, $p)
    {
        $this->protocols[$protocolName]['protocols'][] = $p;
    }

    private function reflectionProtocolExists($pName)
    {
        return array_key_exists($pName, $this->protocols);
    }

    private function reflectionProtocolAddMethod($protocolName, $methodInfo)
    {
        $this->protocols[$protocolName]['methods'][$methodInfo['type']][$methodInfo['name']] = $methodInfo;
    }

    private function reflectionProtocolInstanceMethods($protocolName)
    {
        return $this->protocols[$protocolName]['methods']['i'];
    }

    private function reflectionProtocolClassMethods($protocolName)
    {
        return $this->protocols[$protocolName]['methods']['i'];
    }

    private function reflectionClassAddMethod($className, $methodInfo, $methodSource, $dispatchMethod)
    {
        // FIXME: stupid form
        $this->classes[$className]['methods'][$methodInfo['type']][$methodInfo['name']] = $methodInfo;
        $this->classes[$className]['methods'][$methodInfo['type']][$methodInfo['name']]['source'] = $methodSource;
        $this->classes[$className]['methods'][$methodInfo['type']][$methodInfo['name']]['dispatchmethod'] = $dispatchMethod;

        //return $methodInfo['name'];
    }

    private function reflectionClassProtocols($name)
    {
        if (isset($this->classes[$name]['protocols']))
            return $this->classes[$name]['protocols'];
        else
            return false;
    }

    private function reflectionClassClassMethods($name)
    {
        return $this->classes[$name]['methods']['c'];
    }

    private function reflectionClassInstanceMethods($name)
    {
        return $this->classes[$name]['methods']['i'];
    }

    private function reflectionClassClassMethodExists($className, $methodName)
    {
        return array_key_exists($methodName, $this->classes[$className]['methods']['c']);
    }

    private function reflectionClassInstanceMethodExists($className, $methodName)
    {
        return array_key_exists($methodName, $this->classes[$className]['methods']['i']);
    }

    private function reflectionClassAddProperty($className, $iVarName, $iVarVis, $iVarType, $iVarInitialValue, $iVarGetAccessor = false, $iVarSetAccessor = false)
    {
        // ivar name, if no Vis is set default is protected
        if (!$iVarVis)
            $iVarVis = 'protected';

        $this->classes[$className]['properties'][$iVarName]['vis'] = $iVarVis;
        $this->classes[$className]['properties'][$iVarName]['type'] = $iVarType;
        $this->classes[$className]['properties'][$iVarName]['value'] = $iVarInitialValue;

        if ($iVarSetAccessor)
        {
            $this->generateSetAccessor($className, $iVarName);
        }
        if ($iVarGetAccessor)
        {
            $this->generateGetAccessor($className, $iVarName);
        }
    }

    private function reflectionClassAddPropertyWithProperty($className, $property)
    {
        array_merge($this->classes[$className]['properties'], $property);
    }

    private function reflectionClassPropertyType($className, $iVarName)
    {
        return isset($this->classes[$className]['properties'][$iVarName]['type']);
    }

    private function reflectionClassProperties($className)
    {
        return $this->classes[$className]['properties'];
    }

    private function reflectionClassAddCategory($className, $catName)
    {
        $this->classes[$className]['categories'][$catName]['protocols'] = array();
        $this->classes[$className]['categories'][$catName]['methods']['i'] = array();
        $this->classes[$className]['categories'][$catName]['methods']['c'] = array();
    }

    private function reflectionClassCategoryInstanceMethods($className, $catName)
    {
        return $this->classes[$className]['categories'][$catName]['methods']['i'];
    }

    private function reflectionClassCategoryClassMethods($className, $catName)
    {
        return $this->classes[$className]['categories'][$catName]['methods']['c'];
    }

    private function reflectionClassCategoryAddProtocol($className, $catName, $pName)
    {
        $this->classes[$className]['categories'][$catName]['protocols'][] = $pName;
    }

    private function reflectionClassCategoryProtocols($className, $catName)
    {
        return $this->classes[$className]['categories'][$catName]['protocols'];
    }

    private function reflectionClassCategoryAddMethod($className, $catName, $methodInfo, $methodSource, $dispatchMethod)
    {
        $this->classes[$className]['categories'][$catName]['methods'][$methodInfo['type']][$methodInfo['name']] = $methodInfo;
        $this->classes[$className]['categories'][$catName]['methods'][$methodInfo['type']][$methodInfo['name']]['source'] = $methodSource;
        $this->classes[$className]['categories'][$catName]['methods'][$methodInfo['type']][$methodInfo['name']]['dispatchmethod'] = $dispatchMethod;
    }

    // *********************
    private $startTime = 0.0;
    private $endTime = 0.0;
    private $totalTime = 0.0;

    private function startTimer()
    {
        $mtime = explode(" ", microtime());
        $this->startTime = $mtime[1] + $mtime[0];
    }

    private function stopTimer()
    {
        $mtime = explode(" ", microtime());
        $this->endTime = $mtime[1] + $mtime[0];
        $this->totalTime += ($this->endTime - $this->startTime);
    }

    public function getTime()
    {
        return $this->totalTime;
    }
}
