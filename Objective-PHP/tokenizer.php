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

// Tokens, includes possible future tokens which are currently not used
const T_OBJPHP_IMPORT           = 10000; // @import -- # is a php comment
const T_OBJPHP_IMPLEMENTATION   = 10001; // @implementation
const T_OBJPHP_INTERFACE        = 10002; // @interface
const T_OBJPHP_END              = 10003; // @end
const T_OBJPHP_PUBLIC           = 10004; // @public
const T_OBJPHP_PRIVATE          = 10005; // @private
const T_OBJPHP_PROTECTED        = 10006; // @protected
const T_OBJPHP_TRY              = 10007; // @try
const T_OBJPHP_CATCH            = 10008; // @catch
const T_OBJPHP_THROW            = 10009; // @implementation
const T_OBJPHP_FINALLY          = 10010; // @finally
const T_OBJPHP_PROTOCOL         = 10011; // @protocol
const T_OBJPHP_SELECTOR         = 10012; // @selector
const T_OBJPHP_SYNTHESIZE       = 10013; // @synthesize
const T_OBJPHP_ACCESSORS        = 10014; // @accessors
const T_OBJPHP_SYNCHRONIZED     = 10015; // @synchronized
const T_OBJPHP_DEFS             = 10016; // @defs
const T_OBJPHP_ENCODE           = 10017; // @encode
const T_OBJPHP_PHP              = 10018; // @php
const T_OBJPHP_CMD              = 10019; // $_cmd
const T_OBJPHP_SELF             = 10020; // $self: (Note: self (no $) is reserved)
const T_OBJPHP_NIL              = 10021; // nil
const T_OBJPHP_OBJNIL           = 10022; // Nil
const T_OBJPHP_THIS             = 10023; // $this: (In instance methods this is replaced by $_op_receiver)
const T_OBJPHP_YES              = 10024; // YES
const T_OBJPHP_NO               = 10025; // NO

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

    private $tokenMap
        = array(
            "@accessors"            => T_OBJPHP_ACCESSORS,
            "@catch"                => T_OBJPHP_CATCH,
            "@end"                  => T_OBJPHP_END,
            "@finally"              => T_OBJPHP_FINALLY,
            "@implementation"       => T_OBJPHP_IMPLEMENTATION,
            "@import"               => T_OBJPHP_IMPORT,
            "@php"                  => T_OBJPHP_PHP,
            "@private"              => T_OBJPHP_PRIVATE,
            "@protected"            => T_OBJPHP_PROTECTED,
            "@protocol"             => T_OBJPHP_PROTOCOL,
            "@public"               => T_OBJPHP_PUBLIC,
            "@selector"             => T_OBJPHP_SELECTOR,
            "@throw"                => T_OBJPHP_THROW,
            "@try"                  => T_OBJPHP_TRY,
            '$self'                 => T_OBJPHP_SELF,
            '$this'                 => T_OBJPHP_THIS,
            "_cmd"                  => T_OBJPHP_CMD,
            "nil"                   => T_OBJPHP_NIL,
            "Nil"                   => T_OBJPHP_OBJNIL,
            "NO"                    => T_OBJPHP_NO,
            "YES"                   => T_OBJPHP_YES
        );

    private $tokenNames
        = array(
            T_OBJPHP_ACCESSORS      => "T_OBJPHP_ACCESSORS",
            T_OBJPHP_CATCH          => "T_OBJPHP_CATCH",
            T_OBJPHP_END            => "T_OBJPHP_END",
            T_OBJPHP_FINALLY        => "T_OBJPHP_FINALLY",
            T_OBJPHP_IMPLEMENTATION => "T_OBJPHP_IMPLEMENTATION",
            T_OBJPHP_IMPORT         => "T_OBJPHP_IMPORT",
            T_OBJPHP_PHP            => "T_OBJPHP_PHP",
            T_OBJPHP_PRIVATE        => "T_OBJPHP_PRIVATE",
            T_OBJPHP_PROTECTED      => "T_OBJPHP_PROTECTED",
            T_OBJPHP_PROTOCOL       => "T_OBJPHP_PROTOCOL",
            T_OBJPHP_PUBLIC         => "T_OBJPHP_PUBLIC",
            T_OBJPHP_SELECTOR       => "T_OBJPHP_SELECTOR",
            T_OBJPHP_THROW          => "T_OBJPHP_THROW",
            T_OBJPHP_TRY            => "T_OBJPHP_TRY",
            T_OBJPHP_SELF           => "T_OBJPHP_SELF",
            T_OBJPHP_THIS           => "T_OBJPHP_THIS",
            T_OBJPHP_CMD            => "T_OBJPHP_CMD",
            T_OBJPHP_NIL            => "T_OBJPHP_NIL",
            T_OBJPHP_OBJNIL         => "T_OBJPHP_OBJNIL",
            T_OBJPHP_NO             => "T_OBJPHP_NO",
            T_OBJPHP_YES            => "T_OBJPHP_YES"
        );

    public function createToken($id, $text, $curline)
    {
        $name = (is_int($id))?(
                                (($tname = token_name($id)) != "UNKNOWN")?($tname):(
                                        (($tname = $this->tokenName($id)) !== false)?($tname):($text)
                                    )
                              ):($text);

        return array($id, $name, $text, $curline);
    }

    public function tokenName($id)
    {
        return (array_key_exists($id, $this->tokenNames))?($this->tokenNames[$id]):(false);
    }

    private function tokenize($src)
    {
        $this->startTimer();

        $tokenizedSource = token_get_all($src);

        $curline = -1; // keeps the current line counter for the string tokens
        $tokenCount = count($tokenizedSource);

        for ($i = 0; $i < $tokenCount; $i++)
        {
            $token = $tokenizedSource[$i];

            $prevToken = ($i > 0)?($tokenizedSource[$i-1]):(false);

            // Many single character tokens are simply retained as strings
            // Name, string and id are all simply the character string
            if (is_string($token))
            {
                $tokenizedSource[$i] = $this->createToken($token, $token, $curline);
                continue;
            }

            // PHP tokens are Array objects which contain the ID, the string of the token
            // and the location of the token (source code line).
            list($id, $text, $curline) = $token;

            // Create key into token map
            if($prevToken !== false && $prevToken[0] == '@')
            {
                $key = '@'.$text;
                $rmToken = true;
            }
            else
                $key = $text;

            // Check if this is an Objective PHP keyword
            if (array_key_exists($key, $this->tokenMap))
            {
                // Delete preceeding '@' from token stream
                if (isset($rmToken))
                {
                    unset($tokenizedSource[$i - 1]);
                    unset($rmToken);
                }

                $tokenizedSource[$i] = $this->createToken($this->tokenMap[$key], $text, $curline);
            }
            else
            {
                if (isset($rmToken))
                    unset($rmToken);

                $tokenizedSource[$i] = $this->createToken($id, $text, $curline);
            }
        }

        // add new tokens
        array_splice($this->tokenChain, $this->tokenIndex, 0, $tokenizedSource);

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
