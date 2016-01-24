<?php
require_once '../vendor/autoload.php';

$XMPP = new \BirknerAlex\XMPPHP\XMPP('chat.fritz.box', 5222, 'katja.weiss', 'test123', 'PHP');

$XMPP->connect();
$XMPP->processUntil('session_start', 10);
$XMPP->presence();
$XMPP->message('christian.weiss@chat.fritz.box', 'Send by PHP', 'chat');
$XMPP->disconnect();
echo "Test done.";