Getting Started With XMPPHP
===========================

XMPPHP is a fork of svn://netflint.net/xmpphp.
This is an elegant PHP library for XMPP (aka Jabber, Google Talk, etc).

Author: Nathan Fritz, jabber id: fritzy [at] netflint.net
Co-Author: Stephan Wentz, jabber id: stephan [at] wentz.it
forked from: Alexander Birkner <BirknerAlex>
current fork by: Thomas Bähr

## Prerequisites

+ PHP 5.3.2 or newer
+ SSL Support Compiled

## Installation

Installation is a quick and easy 2 steps process:

1. Install XMPPHP
2. Use it

### Step 1: Install XMPPHP

The preferred way to install this bundle is to rely on [Composer](http://getcomposer.org).

TBD

### Step 2: Use it

This sample shows how to send a Jabber message to a user.

``` php
<?php


require_once './vendor/autoload.php';

$XMPP = new \Dromsal\XMPPHP\XMPP('jabber.domain.com', 5222, 'firstname.lastname', 'MySecretPassword', 'PHP');

$XMPP->connect();
$XMPP->processUntil('session_start', 10);
$XMPP->presence();
$XMPP->message('target.user@jabber.domain.com', 'Hello, how are you?', 'chat');
$XMPP->disconnect();

```

Please feel free to add more samples to this Github Repository.

## TODO

* MUC Support

## License Exception

Please contact Nathan Fritz for library exceptions if you would like to
distribute XMPPHP with a non-GPL compatible license.