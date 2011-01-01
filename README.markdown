Objective-PHP and Moka
======================
Released under the LGPL (see COPYING & COPYING.LESSER)
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
