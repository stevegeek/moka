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
class Tokenizer extends \ArrayObject
{
    private $lastErrorCode;
    private $lastError;

    protected $tokenChain;
    private $tokenIndex;

    function __construct($codeObjPHP = null)
    {
        $this->reset();
        parent::__construct($this->tokenChain);

        if($codeObjPHP)
        {
            $this->tokenizeWithReset($codeObjPHP);
        }
    }

    private function reset()
    {
        $this->tokenIndex = 0;
        $this->tokenChain = array();
    }

    public function getLastError()
    {
        return array("code" => $this->lastErrorCode, "text" => $this->lastError);
    }

    public function addTokens($codeObjPHP)
    {
        $this->tokenize($codeObjPHP);
    }

    public function addTokensAndReset($codeObjPHP)
    {
        $this->reset();
        $this->addTokens($codeObjPHP);
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
                // $token is an Array object
                list($id,$text,$curline) = $token;
                $t_name = token_name($id); // PHP token name function

                // Objective-PHP Keywords
                switch($id)
                {
                    case T_PUBLIC:
                        if ($prevToken && ($prevToken[0] == '@'))
                        {
                            $id = T_OBJPHP_PUBLIC;
                            $t_name = "T_OBJPHP_PUBLIC";
                            $rmToken = true;
                        }
                        break;
                    case T_PRIVATE:
                        if ($prevToken && ($prevToken[0] == '@'))
                        {
                            $id = T_OBJPHP_PRIVATE;
                            $t_name = "T_OBJPHP_PRIVATE";
                            $rmToken = true;
                        }
                        break;
                    case T_PROTECTED:
                        if ($prevToken && ($prevToken[0] == '@'))
                        {
                            $id = T_OBJPHP_PROTECTED;
                            $t_name = "T_OBJPHP_PROTECTED";
                            $rmToken = true;
                        }
                        break;
                    case T_TRY:
                        if ($prevToken && ($prevToken[0] == '@'))
                        {
                            $id = T_OBJPHP_TRY;
                            $t_name = "T_OBJPHP_TRY";
                            $rmToken = true;
                        }
                        break;
                    case T_CATCH:
                        if ($prevToken && ($prevToken[0] == '@'))
                        {
                            $id = T_OBJPHP_CATCH;
                            $t_name = "T_OBJPHP_CATCH";
                            $rmToken = true;
                        }
                        break;

                    case T_VARIABLE:
                        // Objective-PHP Constants
                        switch($text)
                        {
                            case '$self':
                                $id = T_OBJPHP_SELF;
                                $t_name = "T_OBJPHP_SELF";
                                break 2;
                            case '$this':
                                //$id = T_OBJPHP_SELF;
                                //$t_name = "T_OBJPHP_SELF";
                                $id = T_OBJPHP_THIS;
                                $t_name = "T_OBJPHP_THIS";
                                break 2;
                        }
                        break;

                    case T_STRING:
                        // Objective-PHP Constants
                        switch ($text)
                        {
                            case "_cmd":
                                $id = T_OBJPHP_CMD;
                                $t_name = "T_OBJPHP_CMD";
                                break;
                            case 'nil':
                                $id = T_OBJPHP_NIL;
                                $t_name = "T_OBJPHP_NIL";
                                break;
                            case 'Nil':
                                $id = T_OBJPHP_OBJNIL;
                                $t_name = "T_OBJPHP_OBJNIL";
                                break;
                            case 'NO':
                                $id = T_OBJPHP_NO;
                                $t_name = "T_OBJPHP_NO";
                                break;
                            case 'YES':
                                $id = T_OBJPHP_YES;
                                $t_name = "T_OBJPHP_YES";
                                break;
                        }

                        // Objective-PHP Keywords
                        if ($prevToken && ($prevToken[0] == '@'))
                        {
                            $rmToken = true;

                            switch($text)
                            {
                                case "import":
                                    $id = T_OBJPHP_IMPORT;
                                    $t_name = "T_OBJPHP_IMPORT";
                                    break;
                                case "implementation":
                                    $id = T_OBJPHP_IMPLEMENTATION;
                                    $t_name = "T_OBJPHP_IMPLEMENTATION";
                                    break;
                                case "interface":
                                    $id = T_OBJPHP_INTERFACE;
                                    $t_name = "T_OBJPHP_INTERFACE";
                                    break;
                                case "end":
                                    $id = T_OBJPHP_END;
                                    $t_name = "T_OBJPHP_END";
                                    break;
                                case "implementation":
                                    $id = T_OBJPHP_THROW;
                                    $t_name = "T_OBJPHP_THROW";
                                    break;
                                case "finally":
                                    $id = T_OBJPHP_FINALLY;
                                    $t_name = "T_OBJPHP_FINALLY";
                                    break;
                                case "protocol":
                                    $id = T_OBJPHP_PROTOCOL;
                                    $t_name = "T_OBJPHP_PROTOCOL";
                                    break;
                                case "selector":
                                    $id = T_OBJPHP_SELECTOR;
                                    $t_name = "T_OBJPHP_SELECTOR";
                                    break;
                                case "synthesize":
                                    $id = T_OBJPHP_SYNTHESIZE;
                                    $t_name = "T_OBJPHP_SYNTHESIZE";
                                    break;
                                case "accessors":
                                    $id = T_OBJPHP_ACCESSORS;
                                    $t_name = "T_OBJPHP_ACCESSORS";
                                    break;
                                case "synchronized":
                                    $id = T_OBJPHP_SYNCHRONIZED;
                                    $t_name = "T_OBJPHP_SYNCHRONIZED";
                                    break;
                                case "defs":
                                    $id = T_OBJPHP_DEFS;
                                    $t_name = "T_OBJPHP_DEFS";
                                    break;
                                case "encode":
                                    $id = T_OBJPHP_ENCODE;
                                    $t_name = "T_OBJPHP_ENCODE";
                                    break;
                                case "php":
                                    $id = T_OBJPHP_PHP;
                                    $t_name = "T_OBJPHP_PHP";
                                    break;
                                default:
                                    // If not a ObjPHP keyword dont remove prev
                                    $rmToken = false;
                                    break;
                            }
                        }
                        break;
                }

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
