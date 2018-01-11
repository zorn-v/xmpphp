<?php

/**
 * XMPPHP: The PHP XMPP Library
 * Copyright (C) 2008  Nathanael C. Fritz
 * This file is part of SleekXMPP.
 *
 * XMPPHP is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * XMPPHP is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with XMPPHP; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category  xmpphp
 * @package   XMPPHP
 * @author    Nathanael C. Fritz <JID: fritzy@netflint.net>
 * @author    Stephan Wentz <JID: stephan@jabber.wentz.it>
 * @author    Michael Garvin <JID: gar@netflint.net>
 * @copyright 2008 Nathanael C. Fritz
 */

namespace XMPPHP;

/**
 * XMPPHP XMLStream
 *
 * @package   XMPPHP
 * @author    Nathanael C. Fritz <JID: fritzy@netflint.net>
 * @author    Stephan Wentz <JID: stephan@jabber.wentz.it>
 * @author    Michael Garvin <JID: gar@netflint.net>
 * @copyright 2008 Nathanael C. Fritz
 * @version   $Id$
 */
class XMLStream {

  const MILLION = 1000000;

  /**
   * @var resource
   */
  protected $socket;

  /**
   * @var resource
   */
  protected $parser;

  /**
   * @var string
   */
  protected $buffer;

  /**
   * @var integer
   */
  protected $xml_depth = 0;

  /**
   * @var string
   */
  protected $host;

  /**
   * @var integer
   */
  protected $port;

  /**
   * @var string
   */
  protected $stream_start = '<stream>';

  /**
   * @var string
   */
  protected $stream_end = '</stream>';

  /**
   * @var boolean
   */
  protected $disconnected = false;

  /**
   * @var boolean
   */
  protected $sent_disconnect = false;

  /**
   * @var array
   */
  protected $ns_map = array();

  /**
   * @var array
   */
  protected $current_ns = array();

  /**
   * @var array
   */
  protected $xmlobj = null;

  /**
   * @var array
   */
  protected $nshandlers = array();

  /**
   * @var array
   */
  protected $xpathhandlers = array();

  /**
   * @var array
   */
  protected $idhandlers = array();

  /**
   * @var array
   */
  protected $eventhandlers = array();

  /**
   * @var integer
   */
  protected $lastid = 0;

  /**
   * @var string
   */
  protected $default_ns;

  /**
   * @var array
   */
  protected $until = [];

  /**
   * @var array
   */
  protected $until_count = [];

  /**
   * @var array
   */
  protected $until_happened = false;

  /**
   * @var array
   */
  protected $until_payload = array();

  /**
   * @var Log
   */
  protected $log;

  /**
   * @var boolean
   */
  protected $reconnect = true;

  /**
   * @var boolean
   */
  protected $been_reset = false;

  /**
   * @var boolean
   */
  protected $is_server;

  /**
   * @var float
   */
  protected $last_send = 0;

  /**
   * @var boolean
   */
  protected $use_ssl = false;

  /**
   * @var integer
   */
  protected $reconnectTimeout = 30;

  /**
   * counter for number of messages sent
   * @var integer
   */
  protected $sendCount = 0;

  /**
   * Constructor
   *
   * @param string  $host
   * @param string  $port
   * @param boolean $printlog
   * @param string  $loglevel
   * @param boolean $is_server
   */
  public function __construct($host = null, $port = null, $printlog = false, $loglevel = null, $is_server = false) {

    $this->reconnect = (!$is_server);
    $this->is_server = $is_server;
    $this->host      = $host;
    $this->port      = $port;
    $this->setupParser();
    $this->log       = new Log($printlog, $loglevel);
  }

  /**
   * Destructor
   * Cleanup connection
   */
  public function __destruct() {
    if (!$this->disconnected AND $this->socket) {
      $this->disconnect();
    }
  }

  /**
   * Return the log instance
   *
   * @return Log
   */
  public function getLog() {
    return $this->log;
  }

  /**
   * Get next ID
   *
   * @return integer
   */
  public function getId() {
    $this->lastid++;
    return $this->lastid;
  }

  /**
   * Set SSL
   *
   * @return integer
   */
  public function useSSL($use = true) {
    $this->use_ssl = $use;
  }

  /**
   * Add ID Handler
   *
   * @param integer $id
   * @param string  $pointer
   * @param string  $obj
   */
  public function addIdHandler($id, $pointer, $obj = null) {
    $this->idhandlers[$id] = array($pointer, $obj);
  }

  /**
   * Add Handler
   *
   * @param string  $name
   * @param string  $ns
   * @param string  $pointer
   * @param string  $obj
   * @param integer $depth
   */
  public function addHandler($name, $ns, $pointer, $obj = null, $depth = 1) {
    // TODO deprication warning
    $this->nshandlers[] = array($name, $ns, $pointer, $obj, $depth);
  }

  /**
   * Add XPath Handler
   *
   * @param string $xpath
   * @param string $pointer
   * @param
   */
  public function addXPathHandler($xpath, $pointer, $obj = null) {

    if (preg_match_all("/\(?{[^\}]+}\)?(\/?)[^\/]+/", $xpath, $regs)) {
      $ns_tags = $regs[0];
    }
    else {
      $ns_tags = array($xpath);
    }

    foreach ($ns_tags as $ns_tag) {

      list($l, $r) = explode('}', $ns_tag);

      if ($r != null) {
        $xpart = array(substr($l, 1), $r);
      }
      else {
        $xpart = array(null, $l);
      }

      $xpath_array[] = $xpart;
    }

    $this->xpathhandlers[] = array($xpath_array, $pointer, $obj);
  }

  /**
   * Add Event Handler
   *
   * @param integer $id
   * @param string  $pointer
   * @param string  $obj
   */
  public function addEventHandler($name, $pointer, $obj) {
    $this->eventhandlers[] = array($name, $pointer, $obj);
  }

  /**
   * Connect to XMPP Host
   *
   * @param integer $timeout    Timeout in seconds
   * @param boolean $persistent
   * @param boolean $sendinit   Send XMPP starting sequence after connect
   *                            automatically
   *
   * @throws XMPPHP_Exception When the connection fails
   */
  public function connect($timeout = 30, $persistent = false, $sendinit = true) {

    $starttime = time();

    do {

      $this->disconnected    = false;
      $this->sent_disconnect = false;

      if ($persistent) {
        $conflag = STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT;
      }
      else {
        $conflag = STREAM_CLIENT_CONNECT;
      }

      $conntype = ($this->use_ssl) ? 'ssl' : 'tcp';
      $sprintf  = 'Connecting to %s://%s:%s';
      $this->log->log(sprintf($sprintf, $conntype, $this->host, $this->port));

      try {
        $options=array(
          'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
          )
        );
        $streamContext = stream_context_create($options);
        $this->socket = stream_socket_client("$conntype://{$this->host}:{$this->port}", $errno, $errstr, $timeout, $conflag, $streamContext);
      } catch (\Exception $e) {
        throw new Exception($e->getMessage());
      }

      if (!$this->socket) {
        $this->log->log('Could not connect.',  Log::LEVEL_ERROR);
        $this->disconnected = true;
        // Take it easy for a few seconds
        sleep(min($timeout, 5));
      }
    } while (!$this->socket AND ((time() - $starttime) < $timeout));

    if ($this->socket) {

      stream_set_blocking($this->socket, 1);

      if ($sendinit) {
        $this->send($this->stream_start);
      }
    }
    else {
      throw new Exception('Could not connect before timeout.');
    }
  }

  /**
   * Reconnect XMPP Host
   *
   * @throws XMPPHP_Exception When the connection fails
   * @uses   $reconnectTimeout
   * @see    setReconnectTimeout()
   */
  public function doReconnect() {

    if (!$this->is_server) {

      $this->log->log('Reconnecting (' . $this->reconnectTimeout . ')...',  Log::LEVEL_WARNING);
      $this->connect($this->reconnectTimeout, false, false);
      $this->reset();
      $this->event('reconnect');
    }
  }

  public function setReconnectTimeout($timeout) {
    $this->reconnectTimeout = $timeout;
  }

  /**
   * Disconnect from XMPP Host
   */
  public function disconnect() {

    $this->log->log('Disconnecting...',  Log::LEVEL_VERBOSE);

    if ((bool) $this->socket == false) {
      return;
    }

    $this->reconnect       = false;
    $this->send($this->stream_end);
    $this->sent_disconnect = true;
    $this->processUntil('end_stream', 5);
    $this->disconnected    = true;
  }

  /**
   * Are we are disconnected?
   *
   * @return boolean
   */
  public function isDisconnected() {
    return $this->disconnected;
  }

  /**
   * Checks if the given string is closed with the same tag as it is
   * opened. We try to be as fast as possible here.
   *
   * @param string $buff Read buffer of __process()
   *
   * @return boolean true if the buffer seems to be complete
   */
  protected function bufferComplete($buff) {

    if (substr($buff, -1) != '>') {
      return false;
    }

    // We always have a space since the namespace needs to be declared.
    // Could be a tab, though
    $min   = min(strpos($buff, '>', 2), strpos($buff, ' ', 2)) - 1;
    $start = substr($buff, 1, $min);
    $stop  = substr($buff, -strlen($start) - 3);

    if ($start == '?xml' OR substr($start, -6) == 'stream') {
      // Starting with an xml tag. this means a stream is being opened,
      // which is not much of data, so no fear it's not complete
      return true;
    }

    if (substr($stop, -2) == '/>') {
      // One tag, i.e. <success />
      return true;
    }

    if ('</' . $start . '>' == $stop) {
      return true;
    }

    return false;
  }

  /**
   * Core reading tool
   *
   * @param mixed   $maximum Limit when to return
   *                         - 0: only read if data is immediately ready
   *                         - null: wait forever and ever
   *                         - integer: process for this amount of microseconds
   * @param boolean $return  Immediately return when data have been
   *                         received
   *
   * @return boolean true when all goes well, false when something fails
   */
  protected function __process($maximum = 5, $return = false) {


    if ($this->socket !== null) {

      $remaining = $maximum;
      $starttime = (microtime(true) * self::MILLION);

      do {

        $read      = array($this->socket);
        $write     = array();
        $except    = array();

        if (is_null($maximum)) {
          $secs  = null;
          $usecs = null;
        }
        elseif ($maximum == 0) {
          $secs  = 0;
          $usecs = 0;
        }
        else {
          $usecs = $remaining % self::MILLION;
          $secs  = floor(($remaining - $usecs) / self::MILLION);
        }

        $updated = stream_select($read, $write, $except, $secs, $usecs);
        $buff = '';
        
        if ($updated === false) {

          $this->log->log('Error on stream_select()',  Log::LEVEL_VERBOSE);

          if ($this->reconnect) {
            $this->doReconnect();
          }
          else {

            if (!empty($this->socket)) {
              fclose($this->socket);
            }

            $this->socket       = null;
            $this->disconnected = true;

            return false;
          }
        }
        elseif ($updated > 0) {

          do {

            if ($buff != '') {
              // Disable blocking for now because fread() will block until
              // the 4k are full if we already read a part of the packet
              stream_set_blocking($this->socket, 0);
            }

            $part = fread($this->socket, 4096);
            stream_set_blocking($this->socket, 1);

            if ($part === false) {

              if ($this->reconnect) {
                $this->doReconnect();
              }
              else {
                fclose($this->socket);
                $this->socket = null;

                return false;
              }
            }

            // Just to avoid a lot of blank fread result
            if (trim($part) != '') {
              $this->log->log('RECV: ' . $part,  Log::LEVEL_VERBOSE);
            }

            $buff     .= $part;
            $endtime   = (microtime(true) * self::MILLION);
            $time_past = $endtime - $starttime;
            $remaining = $remaining - $time_past;

          } while ((is_null($maximum) OR $remaining > 0) AND !$this->bufferComplete($buff) AND !feof($this->socket));
        }

        if (trim($buff) != '') {

          xml_parse($this->parser, $buff, false);

          // Return when received
          if ($return) {
            return true;
          }
        }
        else {
          // $updated == 0 means no changes during timeout.
        }

        $endtime   = (microtime(true) * self::MILLION);
        $time_past = $endtime - $starttime;
        $remaining = $remaining - $time_past;

      } while (is_null($maximum) OR $remaining > 0);
    }

    return true;
  }

  /**
   * Process
   *
   * @return string
   */
  public function process() {
    $this->__process(null);
  }

  /**
   * Process until a timeout occurs
   *
   * @param integer $timeout Time in seconds
   *
   * @return string
   *
   * @see __process()
   */
  public function processTime($timeout = null) {

    if (is_null($timeout)) {
      return $this->__process(null);
    }
    else {
      return $this->__process($timeout * self::MILLION);
    }
  }

  /**
   * Process until next event or a timeout occurs
   *
   * @param integer $timeout Time in seconds
   *
   * @return string
   *
   * @see __process()
   */
  public function processUntilNext($timeout = null) {

    if (is_null($timeout)) {
      return $this->__process(null, true);
    }
    else {
      return $this->__process($timeout * self::MILLION, true);
    }
  }

  /**
   * Process until a specified event or a timeout occurs
   *
   * @param string|array $event   Event name or array of event names
   * @param integer      $timeout Timeout in seconds
   *
   * @return array Payload
   */
  public function processUntil($event, $timeout = -1) {

    $start = time();

    if (!is_array($event)) {
      $event = array($event);
    }

    $this->until[] = $event;
    end($this->until);
    $event_key     = key($this->until);
    reset($this->until);

    $this->until_count[$event_key] = 0;

    while (!$this->disconnected AND $this->until_count[$event_key] < 1
      AND ($timeout == -1 OR ((time() - $start) < $timeout))) {

      $maximum = ($timeout == -1) ? null : ($timeout - (time() - $start)) * self::MILLION;
      $return  = $this->__process($maximum, true);

      if (!$return) {
        break;
      }
    }

    if (array_key_exists($event_key, $this->until_payload)) {

      $payload = $this->until_payload[$event_key];
      unset($this->until_payload[$event_key]);
      unset($this->until_count[$event_key]);
      unset($this->until[$event_key]);
    }
    else {
      $payload = array();
      unset($this->until_count[$event_key]);
      unset($this->until[$event_key]);
    }

    return $payload;
  }

  /**
   * Obsolete?
   */
  public function Xapply_socket($socket) {
    $this->socket = $socket;
  }

  /**
   * XML start callback
   *
   * @see xml_set_element_handler
   *
   * @param resource $parser
   * @param string   $name
   */
  public function startXML($parser, $name, $attr) {

    if ($this->been_reset) {
      $this->been_reset = false;
      $this->xml_depth  = 0;
    }

    $this->xml_depth++;

    if (array_key_exists('XMLNS', $attr)) {
      $this->current_ns[$this->xml_depth] = $attr['XMLNS'];
    }
    else {

      $this->current_ns[$this->xml_depth] = $this->current_ns[$this->xml_depth - 1];

      if (!$this->current_ns[$this->xml_depth]) {
        $this->current_ns[$this->xml_depth] = $this->default_ns;
      }
    }

    $ns = $this->current_ns[$this->xml_depth];

    foreach ($attr as $key => $value) {

      if (strstr($key, ':')) {

        $key                = explode(':', $key);
        $key                = $key[1];
        $this->ns_map[$key] = $value;
      }
    }

    if (!strstr($name, ':') === false) {
      $name = explode(':', $name);
      $ns   = $this->ns_map[$name[0]];
      $name = $name[1];
    }

    $obj = new XMLObj($name, $ns, $attr);

    if ($this->xml_depth > 1) {
      $this->xmlobj[$this->xml_depth - 1]->subs[] = $obj;
    }

    $this->xmlobj[$this->xml_depth] = $obj;
  }

  /**
   * XML end callback
   *
   * @see xml_set_element_handler
   *
   * @param resource $parser
   * @param string   $name
   */
  public function endXML($parser, $name) {

    // This print a lot of messages
    //$this->log->log('Ending ' . $name,  XMPPHP_Log::LEVEL_DEBUG);

    if ($this->been_reset) {
      $this->been_reset = false;
      $this->xml_depth  = 0;
    }

    $this->xml_depth--;

    if ($this->xml_depth == 1) {

      // Clean-up old objects

      foreach ($this->xpathhandlers as $handler) {

        if (is_array($this->xmlobj) AND array_key_exists(2, $this->xmlobj)) {

          $searchxml  = $this->xmlobj[2];
          $nstag      = array_shift($handler[0]);
          $condition1 = ($nstag[0] == null OR $searchxml->ns == $nstag[0]);
          $condition2 = ($nstag[1] == '*' OR $nstag[1] == $searchxml->name);

          if ($condition1 AND $condition2) {

            foreach ($handler[0] as $nstag) {

              if ($searchxml !== null AND $searchxml->hasSub($nstag[1], $ns = $nstag[0])) {
                $searchxml = $searchxml->sub($nstag[1], $ns = $nstag[0]);
              }
              else {
                $searchxml = null;
                break;
              }
            }

            if ($searchxml !== null) {

              if (is_object($handler[1]) AND is_callable($handler[1])) {
                $this->log->log('Calling Closure',  Log::LEVEL_DEBUG);
                $handler[1]($this->xmlobj[2]);
              }
              else {

                if ($handler[2] === null) {
                  $handler[2] = $this;
                }

                $this->log->log('Calling ' . $handler[1],  Log::LEVEL_DEBUG);
                call_user_func([$handler[2], $handler[1]], $this->xmlobj[2]);
              }
            }
          }
        }
      }

      foreach ($this->nshandlers as $handler) {

        $condition1 = ($handler[4] != 1);
        $condition2 = (array_key_exists(2, $this->xmlobj));
        $condition3 = ($this->xmlobj[2]->hasSub($handler[0]));

        if ($condition1 AND $condition2 AND $condition3) {
          $searchxml = $this->xmlobj[2]->sub($handler[0]);
        }
        elseif (is_array($this->xmlobj) AND array_key_exists(2, $this->xmlobj)) {
          $searchxml = $this->xmlobj[2];
        }

        $condition1 = ($searchxml !== null AND $searchxml->name == $handler[0]);
        $condition2 = ($searchxml->ns == $handler[1]);
        $condition3 = (!$handler[1] AND $searchxml->ns == $this->default_ns);

        if ($condition1 AND ($condition2 OR $condition3)) {

          if ($handler[3] === null) {
            $handler[3] = $this;
          }

          $this->log->log('Calling ' . $handler[2],  Log::LEVEL_DEBUG);
          call_user_func([$handler[3], $handler[2]], $this->xmlobj[2]);
        }
      }

      foreach ($this->idhandlers as $id => $handler) {

        $condition1 = (array_key_exists('id', $this->xmlobj[2]->attrs));
		if (isset($this->xmlobj[2]->attrs['id'])) {
		  $condition2 = ($this->xmlobj[2]->attrs['id'] == $id);
		} else {
 		  $condition2 = false;
		}

        if ($condition1 AND $condition2) {

          if ($handler[1] === null) {
            $handler[1] = $this;
          }

          call_user_func([$handler[1], $handler[0]], $this->xmlobj[2]);
          // The handlers id are only used once
          unset($this->idhandlers[$id]);
          break;
        }
      }

      if (is_array($this->xmlobj)) {

        $this->xmlobj = array_slice($this->xmlobj, 0, 1);

        if (isset($this->xmlobj[0]) AND $this->xmlobj[0] INSTANCEOF XMLObj) {
          $this->xmlobj[0]->subs = null;
        }
      }

      unset($this->xmlobj[2]);
    }

    if ($this->xml_depth == 0 AND !$this->been_reset) {

      if (!$this->disconnected) {

        if (!$this->sent_disconnect) {
          $this->send($this->stream_end);
        }

        $this->disconnected    = true;
        $this->sent_disconnect = true;
        fclose($this->socket);

        if ($this->reconnect) {
          $this->doReconnect();
        }
      }

      $this->event('end_stream');
    }
  }

  /**
   * XML character callback
   * @see xml_set_character_data_handler
   *
   * @param resource $parser
   * @param string   $data
   */
  public function charXML($parser, $data) {
    if (array_key_exists($this->xml_depth, $this->xmlobj)) {
      $this->xmlobj[$this->xml_depth]->data .= $data;
    }
  }

  /**
   * Event?
   *
   * @param string $name
   * @param string $payload
   */
  public function event($name, $payload = null) {

    $this->log->log('EVENT: ' . $name,  Log::LEVEL_DEBUG);

    foreach ($this->eventhandlers as $handler) {

      if ($name == $handler[0]) {

        if ($handler[2] === null) {
          $handler[2] = $this;
        }

        call_user_func([$handler[2], $handler[1]], $payload);
      }
    }

      foreach($this->until as $key => $until) {

        if (is_array($until) AND in_array($name, $until)) {

          $this->until_payload[$key][] = array($name, $payload);

          if (!isset($this->until_count[$key])) {
            $this->until_count[$key] = 0;
          }

          $this->until_count[$key] += 1;
          // $this->until[$key] = false;
        }
      }
  }

  /**
   * Read from socket
   */
  public function read() {

    if ($this->socket !== null) {
      return false;
    }

    $buff = fread($this->socket, 1024);

    if (!$buff) {

      if ($this->reconnect) {
        $this->doReconnect();
      }
      else {
        fclose($this->socket);

        return false;
      }
    }

    $this->log->log('RECV: ' . $buff,  Log::LEVEL_VERBOSE);
    xml_parse($this->parser, $buff, false);
  }

  /**
   * Send to socket
   *
   * @param string $msg
   */
  public function send($msg, $timeout=null) {

    if (is_null($timeout)) {
      $secs  = null;
      $usecs = null;
    }
    elseif ($timeout == 0) {
      $secs  = 0;
      $usecs = 0;
    }
    else {
      $maximum = $timeout * self::MILLION;
      $usecs   = $maximum % self::MILLION;
      $secs    = floor(($maximum - $usecs) / self::MILLION);
    }

    $read   = array();
    $write  = array($this->socket);
    $except = array();
    $select = stream_select($read, $write, $except, $secs, $usecs);

    if ($select === false) {

      $this->log->log('ERROR sending message; reconnecting.');
      $this->doReconnect();
      // TODO: retry send here

      return false;
    }
    elseif ($select > 0) {
      $this->log->log('Socket is ready, send it.', Log::LEVEL_VERBOSE);
    }
    else {
      $this->log->log('Socket is not ready, break.', Log::LEVEL_ERROR);

      return false;
    }

    $sentbytes = fwrite($this->socket, $msg);
    $this->log->log('SENT: ' . mb_substr($msg, 0, $sentbytes, '8bit'), Log::LEVEL_VERBOSE);

    if ($sentbytes === false) {
      $this->log->log('ERROR sending message; reconnecting.', Log::LEVEL_ERROR);
      $this->doReconnect();

      return false;
    }

    $this->log->log('Successfully sent ' . $sentbytes . ' bytes', Log::LEVEL_VERBOSE);

    $this->sendCount++;

    return $sentbytes;
  }

  public function time() {
    list($usec, $sec) = explode(' ', microtime());
    return (float) $sec + (float) $usec;
  }

  /**
   * Reset connection
   */
  public function reset() {

    $this->xml_depth = 0;
    unset($this->xmlobj);
    $this->xmlobj = array();
    $this->setupParser();

    if (!$this->is_server) {
      $this->send($this->stream_start);
    }

    $this->been_reset = true;
  }

  /**
   * Setup the XML parser
   */
  public function setupParser() {

    $this->parser = xml_parser_create('UTF-8');
    xml_parser_set_option($this->parser, XML_OPTION_SKIP_WHITE, 1);
    xml_parser_set_option($this->parser, XML_OPTION_TARGET_ENCODING, 'UTF-8');
    xml_set_object($this->parser, $this);
    xml_set_element_handler($this->parser, 'startXML', 'endXML');
    xml_set_character_data_handler($this->parser, 'charXML');
  }

  public function readyToProcess() {

    $read    = array($this->socket);
    $write   = array();
    $except  = array();
    $updated = stream_select($read, $write, $except, 0);

    return (($updated !== false) AND ($updated > 0));
  }

  public function getSendCount() {
    return $this->sendCount;
  }
}
