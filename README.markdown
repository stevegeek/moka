Objective-PHP and Moka
======================
Objective-PHP released under the BSD 3-clause license. Moka currently released under the LGPL (see COPYING & COPYING.LESSER)
Copyright 2009, 2010 Stephen Paul Ierodiaconou <stephen@flat53.com>
----------------------

**Objective-PHP** is a port of the Objective-C (or [Objective-J](http://www.cappuccino.org/) )
runtime to PHP. This adds the language features of Objective-C nestled nicely inside the syntax of
Objective-C.

**Moka** is a port of the Apple Cocoa Frameworks (or [Cappuccino](http://www.cappuccino.org/) ). As
PHP is primarily a server side scripting language the frameworks are currently non-UI ones.

I created this a way of learning the Objective-C/J languages in as much depth as possible. However,
through this process a programming language and set of tools has resulting and as such has been
released as Open Source in the hope others too can learn and find use from it. Also since the
original goal was to learn I have created this site and as much documentation and tutorials as
I could muster so others too could effectively follow this learning path. I hope this complements
the skills of the Objective-J Cappuccino programmers, who may program the backend in the well
estabilished PHP while still using the syntax and Objects they are so familiar with.

What?
-----
* A strict superset of PHP (you can use normal PHP anywhere and as much as you like)
* Smalltalk 80 / Objective-C object model
* Dynamic dispatch and delegate programming
* A programming philosophy

Features:
---------
* Objective-PHP & Moka will work happily alongside any PHP Framework
* Protocols
* Categories
* Command line build tools
* Cross platform (all you need is PHP)

Why?
----
* It was fun and interesting to develop!
* Ease of development for devs using Objective-C/J and Cocoa/Cappuccino
* Delegate pattern without extra coding, Objective-C/J like programming
* Cocoa-like Frameworks (Non UI ones)
* Ability to message nil (null)
* Nicer syntax for dynamic dispatch

How?
----
Objective-C is simply C, a runtime and a preprocessor. Even if the compiler does not do it, it would
be perfectly possible to translate Objective-C into C and the runtime.
Equivalently Objective-PHP is PHP, a runtime and a preprocessor. The language can either be
interpreted at runtime (into PHP) or for deployment and better performance, preprocessed to generate
pure PHP.

----
Copyright (c) 2009-2011, Stephen Ierodiaconou
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:
    * Redistributions of source code must retain the above copyright
      notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.
    * Neither the name of Stephen Ierodiaconou nor the
      names of its contributors may be used to endorse or promote products
      derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
