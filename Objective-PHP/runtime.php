<?php
/*
 * runtime.php
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

include_once "Objective-PHP/tokenizer.php";
include_once "Objective-PHP/parser.php";

// Error Codes
const UNDEF_ERR                     = 0;
const RUNTIME_ERROR                 = 1;
const RUNTIME_IMPORTPARSER_ERROR    = 2;
const RUNTIME_STUBMETHOD_ERROR      = 3;

// Internal Constants
const _METHOD_CAT           = 1;
const _METHOD_CLASS         = 2;
const _CLS_META             = 1;
const _CLS_CLASS            = 2;
const _CLS_PROTOCOL         = 3;

// FIXME: DO AWAY WITH THIS ... simply at compile time pass the preprocessor to each method?
$_objphp_preprocessor = null;

// Note: for the root class see Moka/Object.php
// The base runtime class for instance objects
class _class
{
    public $isa;
}

// The base runtime class for class and metaclasses
class _runtimeclass extends _class
{
    public $super_class     = null;
    public $name            = "";
    public $dispatchTable   = array();
    protected $protocols    = array();
    protected $version      = 0;
    //protected $info;
    //protected $instance_size;
    // subclass_list
    // sibling_list
    //protected $cache;
    //protected $ivars;

    public function addProtocol($obj)
    {
        $this->protocols[] = $obj;
    }

    public function getProtocols()
    {
        return $this->protocols;
    }

    public function addMethod($sel, $func)
    {
        $this->dispatchTable[methodNameFromSelector($sel)] = $func;
    }
    public function addMethodWithMethodName($methodName, $func)
    {
        $this->dispatchTable[$methodName]['pointer'] = $func;
        $this->dispatchTable[$methodName]['dispatchmethod'] = _METHOD_CAT;
    }

    // For speed this method does not simply call hasMethodWithMethodName
    public function hasMethod($sel)
    {
        // FIXME: optimise away this call
        $methodName = methodNameFromSelector($sel);

        if (array_key_exists($methodName,$this->dispatchTable))
            return $this->dispatchTable[$methodName]['dispatchmethod'];

        return false;
    }

    public function hasMethodWithMethodName($methodName)
    {
        if (array_key_exists($methodName,$this->dispatchTable))
            return $this->dispatchTable[$methodName]['dispatchmethod'];

        return false;
    }

    public function getMethodFromDispatchTable($sel)
    {
        return $this->dispatchTable[methodNameFromSelector($sel)]['pointer'];
    }
    public function getMethodFromDispatchTableWithMethodName($methodName)
    {
        return $this->dispatchTable[$methodName]['pointer'];
    }

    private function __clone()
    {

    }
}

// The base protocol object, a singleton version of an instance class
class _protocol extends _class
{
    private function __clone()
    {

    }
}

// Runtime logging
function objphp_log()
{
    $argc = func_num_args();
    $argv = func_get_args();

    _objphp_log(vsprintf(array_shift($argv), $argv));
}

function _objphp_log($string)
{
    printf("[".objphp_logCurrentTimeStamp()."] ".$string."\n");
}

function objphp_logCurrentTimeStamp()
{
    return strftime("%x %X");
}

function _objphp_print_trace()
{
    // taken from http://www.php.net/manual/en/function.debug-print-backtrace.php#88176
    $buffer = array (  ) ;
    $trace_calls = "[Trace]\n" ;
    ob_start (  ) ;
    debug_print_backtrace (  ) ;
    $buffer[ "0" ] = ob_get_contents (  ) ;
    ob_end_clean (  ) ;
    $buffer[ "0" ] = array_slice ( explode ( "#" , $buffer[ "0" ] ) , 1 , -1 , false ) ;
    foreach ( $buffer[ "0" ] as $key => $value )
    {
        $value = explode ( ") called at [" , $value ) ;
        if ( $key == 0 )
        {
            $value[ "0" ] = "0  " . __FUNCTION__ . "(see above vars)" ;
        }
        $trace_calls .= "#" . implode ( ")\n\tcalled at [" , $value ) ;
    }
    unset ( $buffer , $key , $value ) ;
    if ( $trace_calls == "" ) $trace_calls = "No functions were called." ;
    echo ( $trace_calls ) ;
}

function nil_method( $receiver, $sel, $params )
{
    // http://www.opensource.apaple.com/source/gcc/gcc-1640/libobjc/nil_method.c
    return $receiver;
}

function objphp_msgSend( $receiver, $methodName, $params, $withSuper=false )
{
    // Note: pass this methodName not selector
    if ( $receiver == null )
        return nil_method( $receiver, $methodName, $params );

    // This warning helps with debugging, but it would be good if it was removed for optimisation purposes.
    if ( !is_object($receiver) || !isset($receiver->isa) )
    {
        _objphp_log("A message has been sent to an non Objective-PHP object. It will fail.");
    }

    // TODO: could copy this function to SendSuper and remove this switch for opt purposes
    if ($withSuper)
        $c = $receiver->isa->super_class;
    else
        $c = $receiver->isa;
    while ($c !== null)
    {
        $mtype = $c->hasMethodWithMethodName($methodName);
        if ($mtype)
        {
            // if the method is a dispatch table method (ie added by category) do
            if ($mtype == _METHOD_CAT)
            {
                // get dtable func pointer
                $func = $c->getMethodFromDispatchTableWithMethodName($methodName);

                return $func($receiver, $params);
                // OR attach $c->$methodName = $func; ... for cacheing? will it help? (prob not)
            }
            else
                return $c->$methodName($receiver, $params);
        }

        $c = $c->super_class;
    }

    // if here the method was not found at all
    // call -forward:: which is on Object thus eventhough its an instance method can be called
    // even on class objects
    if ($methodName === "m_forward__")
    {
        // even forward wasnt found in whole hierarchy, throw runtime exception
        throw new RuntimeException("A message forward '".$params[0]."' failed as 'forward::' was not delivered anywhere on the object hierarchy, are you sure you have implemented 'forward::' in your root Object?");
    }
    else
        return objphp_msgSend( $receiver, "m_forward__", array($methodName,$params));
}

function objphp_msgSendWithSelector( $receiver, $sel, $params)
{
    return objphp_msgSend( $receiver, methodNameFromSelector($sel), $params);
}

// Super Versions
function objphp_msgSendSuper( $receiver, $methodName, $params)
{
    return objphp_msgSend( $receiver, $methodName, $params, true);
}

function objphp_msgSendSuperWithSelector( $receiver, $sel, $params)
{
    return objphp_msgSend( $receiver, methodNameFromSelector($sel), $params, true);
}

// @selector is compile time and MKSelectorFromString is at runtime
function methodNameFromSelector($sel)
{
    return 'm_'.str_replace(":", "_", $sel);
}

function selectorFromMethodName($methodName) // MKStringFromSelector

{
    return str_replace("_", ":", substr($methodName,2));
}

// The PreProcessor object
class PreProcessor
{
    private $tokenizer;
    private $parser;

    private $startExecTime;
    private $endExecTime;

    public function __construct($tokenizer = null, $parser = null)
    {
        if ($tokenizer)
            $this->tokenizer = $tokenizer;
        else
            $this->tokenizer = new Tokenizer();
        if ($parser)
            $this->parser = $parser;
        else
            $this->parser = new Parser($this->tokenizer);


        global $_objphp_preprocessor;
        $_objphp_preprocessor = $this;
    }

    public function loadObjPHPString($code)
    {
        try
        {
            $this->tokenizer->addTokensAndReset($code);
            return $this->parser->parse();
        }
        catch(\ObjPHP\ParseException $e)
        {
            \ObjPHP\_objphp_log("Failed\n---\n".$e->getFormattedError()."\n");
            return false;
        }
        catch(\ObjPHP\CountableException $e)
        {
            \ObjPHP\_objphp_log("Failed\n---\n".$e->getMessage()."\n");
            return false;
        }
    }

    public function loadObjPHPFile($fileName, $rel=true, $runtimeimport=false)
    {
        try
        {
            $this->tokenizer->addTokensAndReset( $this->parser->readImport($fileName, $rel) );
            return $this->parser->parse(null, $runtimeimport);
        }
        catch(\ObjPHP\ParseException $e)
        {
            objphp_log("Failed\n---\n".$e->getFormattedError()."\n");
        }
        catch(\ObjPHP\CountableException $e)
        {
            objphp_log("Failed\n---\n".$e->getMessage()."\n");
        }
        return false;
    }

    public function loadObjPHPFileWithoutReset($fileName, $rel=true, $runtimeimport=false)
    {
        try
        {
            $this->tokenizer->addTokens( $this->parser->readImport($fileName, $rel) );
            return $this->parser->parse(null, $runtimeimport);
        }
        catch(\ObjPHP\ParseException $e)
        {
            objphp_log("Failed\n---\n".$e->getFormattedError()."\n");
        }
        catch(\ObjPHP\CountableException $e)
        {
            objphp_log("Failed\n---\n".$e->getMessage()."\n");
        }
        return false;
    }

    public function run($source, $scopePtr = null)
    {
        $this->startExecutionTimer();
        try
        {
            global $_objphp_preprocessor;
            $_op_obj = $scopePtr;
            // Eval assumes the code is already in PHP mode. So you cannot
            // lead with HTML which is annoying, so intead we close php mode
            // and you MUST open before any objective-php or php in your .op
            // file
            $ret = eval($source);
        }
        catch(\ObjPHP\ParseException $e)
        {
            objphp_log("Failed with Parse Error\n---\n".$e->getFormattedError()."\n");
            return false;
        }
        catch(\ObjPHP\RuntimeException $e)
        {
            objphp_log("Failed with Runtime Error\n---\nR".$e->getMessage()."\n");
            return false;
        }
        catch(\ObjPHP\CountableException $e)
        {
            objphp_log("Failed\n---\n".$e->getMessage()."\n");
            return false;
        }
        $this->stopExecutionTimer();
        return $ret;
    }

    public function Tokenizer()
    {
        return $this->tokenizer;
    }

    public function Parser()
    {
        return $this->parser;
    }

    public function startExecutionTimer()
    {
        $mtime = explode(" ", microtime());
        $this->startExecTime = $mtime[1] + $mtime[0];
    }

    public function stopExecutionTimer()
    {
        $mtime = explode(" ", microtime());
        $this->endExecTime = $mtime[1] + $mtime[0];
    }

    public function getTime()
    {
        return ($this->endExecTime - $this->startExecTime);
    }

    public function reset()
    {
    }
}

// Exception classes
class CountableException extends \Exception
{
    public function __construct($message=null, $code=-1, $previous = null)
    {
        self::incErrorCount();

        parent::__construct($message,$code,$previous);
    }

    protected static $error_count;

    private static function incErrorCount()
    {
        static::$error_count++;
    }

    public static function errorCount()
    {
        return static::$error_count;
    }

}

class ParseException extends CountableException
{

    protected $token;
    protected $message;
    protected $code;

    public function __construct($tokenstruct=null, $message=null, $code=-1, $previous = null)
    {
        $this->token = $tokenstruct;
        $this->message = $message;
        $this->code = $code;

        parent::__construct($message,$code,$previous);
    }

    public function getFormattedError()
    {
        return (($this->token)?("Syntax Error: "):("Parse Error: ")).$this->message.(($this->token)?(" (Line: ".$this->token[3]." token: '".$this->token[1]."'='".$this->token[2]."')\n"):("\n"));
    }
}

class RuntimeException extends CountableException
{
    public function __construct($message=null, $code=-1, $previous = null)
    {
        parent::__construct($message,$code,$previous);
    }
}

class NotImplementedException extends CountableException
{
    public function __construct($sel)
    {
        parent::__construct("'$sel' is not implemented yet!", RUNTIME_STUBMETHOD_ERROR, null);
    }
}
