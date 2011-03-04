<?php
/*
 * runtime.php
 *
 * This file is part of Objective-PHP <http://www.atimport.net/>.
 *
 * Copyright (c) 2009-2011, Stephen Paul Ierodiaconou
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of Stephen Ierodiaconou nor the
 *       names of its contributors may be used to endorse or promote products
 *       derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

include_once "Objective-PHP/tokenizer.php";
include_once "Objective-PHP/parser.php";

// Error Codes
const UNDEF_ERR                     = 0;
const RUNTIME_ERROR                 = 1;
const RUNTIME_IMPORTPARSER_ERROR    = 2;
const RUNTIME_STUBMETHOD_ERROR      = 3;

// Internal Constants
define("_METHOD_CAT",   1);
define("_METHOD_CLASS", 2);
define("_CLS_META",     1);
define("_CLS_CLASS",    2);
define("_CLS_PROTOCOL", 4);

// Note: for the root class see Moka/Object.php
// The base runtime class for instance objects
class _objphp_class
{
    public $isa;
    public $uid;
    protected static $currentuid = 0;

    protected function setUID()
    {
        $this->uid = static::$currentuid++;
    }

    public function __toString()
    {
        try
        {
            $sel = _objphp_methodNameFromSelector('description');
            if ($this->isa->hasMethodWithMethodName($sel))
                return objphp_msgSend($this, $sel, array());
        }
        catch (Exception $e)
        {
        }
        return "[Instance object (".$this->uid.") of class '".$this->isa->name."']";
    }
}

// The base runtime class for class and metaclasses
class _objphp_runtimeclass extends _objphp_class
{
    public $super_class     = null;
    public $name            = "";
    public $dispatchTable   = array();
    protected $protocols    = array();
    protected $version      = 0;
    protected $info         = 0;
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
        $this->dispatchTable[_objphp_methodNameFromSelector($sel)] = $func;
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
        $methodName = _objphp_methodNameFromSelector($sel);

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
        return $this->dispatchTable[_objphp_methodNameFromSelector($sel)]['pointer'];
    }
    public function getMethodFromDispatchTableWithMethodName($methodName)
    {
        return $this->dispatchTable[$methodName]['pointer'];
    }

    private function __clone() { }

    public function __toString()
    {
        try
        {
            $sel = _objphp_methodNameFromSelector('description');
            if ($this->hasMethodWithMethodName($sel))
                return objphp_msgSend($this, $sel, array());
        }
        catch (Exception $e)
        {
        }

        if ($this->info == _CLS_CLASS)
            $type = 'Class';
        else
            $type = 'Metaclass';
        return "[$type object (".$this->uid.") of class '".$this->isa->name."']";
    }
}

// The base protocol object, a singleton version of an instance class
class _objphp_protocol extends _objphp_class
{
    private function __clone() {}

    public function __toString()
    {
        return "[Protocol object (".$this->uid.") of class '".$this->isa->name."']";
    }
}

function objphp_sprintf()
{
    //$argc = func_num_args();
    $argv = func_get_args();
    // intercept objects and call description for %@
    $format = array_shift($argv);

    return vsprintf($format, $argv);
}

// Runtime logging

function objphp_log()
{
    $argv = func_get_args();

    _objphp_log(call_user_func_array("objphp_sprintf",$argv));
}

function objphp_logCurrentTimeStamp()
{
    return strftime("%x %X");
}

function _objphp_log($string)
{
    printf("[".objphp_logCurrentTimeStamp()."] ".$string."\n");
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

// Runtime methods for message dispatch

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
    if ($methodName === _objphp_methodNameFromSelector("forward::"))
    {
        // even forward wasnt found in whole hierarchy, throw runtime exception
        throw new _objphp_RuntimeException("A message forward '".$params[0]."' failed as 'forward::' was not delivered anywhere on the object hierarchy, are you sure you have implemented 'forward::' in your root Object?");
    }
    else
        return objphp_msgSend( $receiver, _objphp_methodNameFromSelector("forward::"), array($methodName,$params));
}

function objphp_msgSendWithSelector( $receiver, $sel, $params)
{
    return objphp_msgSend( $receiver, _objphp_methodNameFromSelector($sel), $params);
}

// Super Versions
function objphp_msgSendSuper( $receiver, $methodName, $params)
{
    return objphp_msgSend( $receiver, $methodName, $params, true);
}

function objphp_msgSendSuperWithSelector( $receiver, $sel, $params)
{
    return objphp_msgSend( $receiver, _objphp_methodNameFromSelector($sel), $params, true);
}

// @selector is compile time and MKSelectorFromString is at runtime
function _objphp_methodNameFromSelector($sel)
{
    return 'm_'.str_replace(":", PARSER_METHOD_COLON_REPLACEMENT, $sel);
}

function _objphp_selectorFromMethodName($methodName) // MKStringFromSelector
{
    return str_replace(PARSER_METHOD_COLON_REPLACEMENT, ":", substr($methodName,2));
}

// The PreProcessor object
class _objphp_PreProcessor
{
    private $tokenizer;
    private $parser;

    private $startExecTime;
    private $endExecTime;

    protected static $instance = NULL;
    private function __clone() { }

    private function __construct($tokenizer = null, $parser = null)
    {
        if ($tokenizer)
            $this->tokenizer = $tokenizer;
        else
            $this->tokenizer = new Tokenizer();
        if ($parser)
            $this->parser = $parser;
        else
            $this->parser = new _objphp_Parser($this->tokenizer);

        static::$instance = $this;
    }

    public static function getInstance($tokenizer = null, $parser = null)
    {
        if (!static::$instance)
            static::$instance = new _objphp_PreProcessor($tokenizer, $parser);

        return static::$instance;
    }

    public function loadObjPHPString($code)
    {
        try
        {
            $this->tokenizer->addTokensAndReset($code);
            return $this->parser->parse();
        }
        catch(_objphp_ParseException $e)
        {
            _objphp_log("Failed\n---\n".$e->getFormattedError()."\n");
            return false;
        }
        catch(_objphp_CountableException $e)
        {
            _objphp_log("Failed\n---\n".$e->getMessage()."\n");
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
        catch(_objphp_ParseException $e)
        {
            objphp_log("Failed\n---\n".$e->getFormattedError()."\n");
        }
        catch(_objphp_CountableException $e)
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
        catch(_objphp_ParseException $e)
        {
            objphp_log("Failed\n---\n".$e->getFormattedError()."\n");
        }
        catch(_objphp_CountableException $e)
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
            $_op_obj = $scopePtr;
            // Eval assumes the code is already in PHP mode. So you cannot
            // lead with HTML which is annoying, so intead we close php mode
            // and you MUST open before any objective-php or php in your .op
            // file
            $ret = eval($source);
        }
        catch(_objphp_ParseException $e)
        {
            objphp_log("Failed with Parse Error\n---\n".$e->getFormattedError()."\n");
            return false;
        }
        catch(_objphp_RuntimeException $e)
        {
            objphp_log("Failed with Runtime Error\n---\nR".$e->getMessage()."\n");
            return false;
        }
        catch(_objphp_CountableException $e)
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
class _objphp_CountableException extends Exception
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

class _objphp_ParseException extends _objphp_CountableException
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

class _objphp_RuntimeException extends _objphp_CountableException
{
    public function __construct($message=null, $code=-1, $previous = null)
    {
        parent::__construct($message,$code,$previous);
    }
}

class _objphp_NotImplementedException extends _objphp_CountableException
{
    public function __construct($sel)
    {
        parent::__construct("'$sel' is not implemented yet!", RUNTIME_STUBMETHOD_ERROR, null);
    }
}
