<?php

/**
 * Created by IntelliJ IDEA.
 * @author Alexabder Birkner alex.birkner@©mail.com
 */

require_once __DIR__.'/../vendor/autoload.php';

$XMPP = new \BirknerAlex\XMPPHP\XMPP('jabber.domain.com', 5222, 'firstname.lastname', 'MySecretPassword', 'PHP');

$XMPP->connect();
$XMPP->processUntil('session_start', 10);
$XMPP->presence();
$XMPP->message('target.user@jabber.domain.com', 'Hello, how are you?', 'chat');
$XMPP->disconnect();