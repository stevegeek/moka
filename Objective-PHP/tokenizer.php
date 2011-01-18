<?php
/*
 * tokenizer.php
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

namespace ObjPHP;

// Tokens
const T_OBJPHP_ATIMPORT =       10000; //@import -- # is a php comment
const T_OBJPHP_IMPLEMENTATION = 10001; //@implementation
const T_OBJPHP_INTERFACE =      10002; //@interface
const T_OBJPHP_END =            10003; //@end
const T_OBJPHP_PUBLIC =         10004; //@public
const T_OBJPHP_PRIVATE =        10005; //@private
const T_OBJPHP_PROTECTED =      10006; //@protected
const T_OBJPHP_TRY =            10007; //@try
const T_OBJPHP_CATCH =          10008; //@catch
const T_OBJPHP_THROW =          10009; //@implementation
const T_OBJPHP_FINALLY =        10010; //@finally
const T_OBJPHP_PROTOCOL =       10011; //@protocol
const T_OBJPHP_SELECTOR =       10012; //@selector
const T_OBJPHP_SYNTHESIZE =     10013; //@synthesize
const T_OBJPHP_ACCESSORS =      10014; //@accessors
const T_OBJPHP_SYNCHRONIZED =   10015; //@synchronized
const T_OBJPHP_DEFS =           10016; //@defs
const T_OBJPHP_ENCODE =         10017; //@encode
const T_OBJPHP_PHP =            10018; //@php

const T_OBJPHP_CMD =            10019; //$_cmd
const T_OBJPHP_SUPER =          10020; //$super
const T_OBJPHP_SELF =           10021; //$self: (Note: self (no $) is reserved)
const T_OBJPHP_NIL =            10022; //nil
const T_OBJPHP_OBJNIL =         10023; //Nil
const T_OBJPHP_THIS =           10024; //$this: (In instance methods this is replaced by $_op_receiver)
const T_OBJPHP_YES =            10025;
const T_OBJPHP_NO =             10026;

// Tokenizer
class Tokenizer
{
    protected $tokenChain;
    private $tokenIndex;

    function __construct($codeObjPHP = null)
    {
        $this->reset();

        if($codeObjPHP)
        {
            $this->addTokens($codeObjPHP);
        }
    }

    public function reset()
    {
        $this->tokenIndex = 0;
        $this->tokenChain = array();
    }

    public function addTokens($codeObjPHP)
    {
        return $this->tokenize($codeObjPHP);
    }

    public function addTokensAndReset($codeObjPHP)
    {
        $this->reset();
        return $this->addTokens($codeObjPHP);
    }

    public function peekToken()
    {
        if ( $this->tokenIndex < count($this->tokenChain) )
            return $this->tokenChain[$this->tokenIndex + 1];
        else
            return false;
    }

    public function current()
    {
        return $this->tokenChain[$this->tokenIndex];
    }

    public function moveNext()
    {
        $this->tokenIndex++;
        if ( $this->tokenIndex < count($this->tokenChain) )
        {
            return $this->tokenChain[$this->tokenIndex];
        }
        else
            return false;
    }

    public function previousToken()
    {
        return $this->previousTokenAt(0);
    }

    public function previousTokenAt($i)
    {
        return ($this->tokenIndex - $i - 1 >= 0)?($this->tokenChain[$this->tokenIndex - $i - 1]):(false);
    }

    // These are the ObjPHP tokens made from an '@' and a PHP token
    private $tokensFromPHPKeywordsAndAtSymbol
        = array(
            T_PUBLIC            => array(T_OBJPHP_PUBLIC,           "T_OBJPHP_PUBLIC"),
            T_PRIVATE           => array(T_OBJPHP_PRIVATE,          "T_OBJPHP_PRIVATE"),
            T_PROTECTED         => array(T_OBJPHP_PROTECTED,        "T_OBJPHP_PROTECTED"),
            T_TRY               => array(T_OBJPHP_TRY,              "T_OBJPHP_TRY"),
            T_CATCH             => array(T_OBJPHP_CATCH,            "T_OBJPHP_CATCH"),
            T_THROW             => array(T_OBJPHP_THROW,            "T_OBJPHP_THROW")
        );

    // These are the ObjPHP tokens made from a PHP variable token with the given text
    private $tokensFromVariables
        = array(
            '$self'             => array(T_OBJPHP_SELF,             "T_OBJPHP_SELF"),
            '$this'             => array(T_OBJPHP_THIS,             "T_OBJPHP_THIS")
        );

    // These are the ObjPHP tokens made from a PHP string token with the given text
    private $tokensFromStrings
        = array(
            "_cmd"              => array(T_OBJPHP_CMD,              "T_OBJPHP_CMD"),
            "nil"               => array(T_OBJPHP_NIL,              "T_OBJPHP_NIL"),
            "Nil"               => array(T_OBJPHP_OBJNIL,           "T_OBJPHP_OBJNIL"),
            "NO"                => array(T_OBJPHP_NO,               "T_OBJPHP_NO"),
            "YES"               => array(T_OBJPHP_YES,              "T_OBJPHP_YES")
        );

    // These are the ObjPHP tokens made from a PHP string token with the given text preceeded by an '@' symbol
    private $tokensFromStringsAndAtSymbol
        = array(
            "php"               => array(T_OBJPHP_PHP,              "T_OBJPHP_PHP"),
            //"encode"            => array(T_OBJPHP_ENCODE,           "T_OBJPHP_ENCODE"),
            //"defs"              => array(T_OBJPHP_DEFS,             "T_OBJPHP_DEFS"),
            //"synchronized"      => array(T_OBJPHP_SYNCHRONIZED,     "T_OBJPHP_SYNCHRONIZED"),
            "accessors"         => array(T_OBJPHP_ACCESSORS,        "T_OBJPHP_ACCESSORS"),
            //"synthesize"        => array(T_OBJPHP_SYNTHESIZE,       "T_OBJPHP_SYNTHESIZE"),
            "selector"          => array(T_OBJPHP_SELECTOR,         "T_OBJPHP_SELECTOR"),
            "protocol"          => array(T_OBJPHP_PROTOCOL,         "T_OBJPHP_PROTOCOL"),
            "import"            => array(T_OBJPHP_ATIMPORT,         "T_OBJPHP_ATIMPORT"),
            "implementation"    => array(T_OBJPHP_IMPLEMENTATION,   "T_OBJPHP_IMPLEMENTATION"),
            //"interface"         => array(T_OBJPHP_INTERFACE,        "T_OBJPHP_INTERFACE"),
            "end"               => array(T_OBJPHP_END,              "T_OBJPHP_END"),
            "finally"           => array(T_OBJPHP_FINALLY,          "T_OBJPHP_FINALLY")
        );

    private function tokenize($src)
    {
        $this->startTimer();

        $tokens = token_get_all($src);

        $curline = -1; // keeps the current line counter for the string tokens
        $tokencount = count($tokens);

        for ($i = 0; $i < $tokencount; $i++)
        {
            $token = $tokens[$i];
            $id = -1;
            $text = "";
            $rmToken = false;

            $prevToken = ($i > 0)?($tokens[$i-1]):(false);

            if (is_string($token))
            {
                // Many single character tokens are simply retained as strings
                // Name, string and id are all simply the character string
                $id = $token;
                $t_name = $token;
                $text = $token;
            }
            else
            {
                // PHP tokens are Array objects which contain the ID, the string of the token
                // and the location of the token (source code line).
                list($id,$text,$curline) = $token;
                $t_name = token_name($id); // PHP token name function

                // Objective-PHP keywords from PHP keywords
                if (array_key_exists($id, $this->tokensFromPHPKeywordsAndAtSymbol))
                {
                    if ($prevToken && ($prevToken[0] == '@'))
                    {
                        $t_name = $this->tokensFromPHPKeywordsAndAtSymbol[$id][1];
                        $id = $this->tokensFromPHPKeywordsAndAtSymbol[$id][0];
                        $rmToken = true;
                    }
                }
                else if ($id == T_VARIABLE && array_key_exists($text, $this->tokensFromVariables))
                {
                    $t_name = $this->tokensFromVariables[$text][1];
                    $id = $this->tokensFromVariables[$text][0];
                }
                else if ($id == T_STRING && array_key_exists($text, $this->tokensFromStrings))
                {
                    $t_name = $this->tokensFromStrings[$text][1];
                    $id = $this->tokensFromStrings[$text][0];
                }
                else if ($id == T_STRING && array_key_exists($text, $this->tokensFromStringsAndAtSymbol))
                {
                    if ($prevToken && ($prevToken[0] == '@'))
                    {
                        $t_name = $this->tokensFromStringsAndAtSymbol[$text][1];
                        $id = $this->tokensFromStringsAndAtSymbol[$text][0];
                        $rmToken = true;
                    }
                }

                // Delete preceeding '@'
                if ($rmToken)
                {
                    unset($tokens[$i - 1]);
                }
            }

            // token is created in our form
            $tokens[$i] = array($id,$t_name,$text,$curline);
        }

        // add new tokens
        array_splice($this->tokenChain, $this->tokenIndex, 0, $tokens);

        $this->stopTimer();
        return true;
    }

    // ITimeable
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
