<?php
define('CRLF', "\r\n");
class HaveToWork extends Thread {
  protected $wait;
  public $socket = null;
  public function __construct() {
    $this->wait = true;
    $this->start();
  }
  public function run() {
    while($this->wait) {
      $this->synchronized(function($thread){
        $thread->wait();
        if($thread->socket != null) {
          echo $thread->getThreadId() . ' > Complete request ' . $thread->socket . "\n\n";
          fwrite(
            $thread->socket, 
              'HTTP/1.1 200 OK' . CRLF
              . 'Server: WebY' . CRLF
              . 'Connection: close' . CRLF  
              . 'Content-Type: text/html;charset=utf-8' . CRLF
              . CRLF
              . 'Hello world'
          );
          fclose($thread->socket);
          $thread->socket = null;        
        }
      }, $this);
      usleep(1000);      
    }
  }
  public function stop() {
    $this->wait = false;
  }
  public function receive(&$socket) {
    if ( $this->isWaiting() ) {
        return $this->synchronized(function($thread, &$socket){
            $thread->socket = $socket;
            echo $thread->getThreadId() . ' > Process request ' . $socket . "\n";
            return $thread->notify();
        }, $this, $socket);   
    } else {
      return false;
    }
  }
}

$nb = 50;
$threads = array();
for($i = 0; $i < $nb; $i++ ) {
  $threads[] = new HaveToWork();
}
$server = stream_socket_server("tcp://0.0.0.0:8010", $errno, $errstr);
if (!$server) {
  echo "Error : $errstr ($errno)\n";
} else {
  echo "Wait with " . count($threads) . " threads \n";
  while ($socket = stream_socket_accept($server)) {
    if ( $socket ) {
      echo '> Receive request ' . $socket . "\n";
      $wait = true;
      while($wait) {
        usleep(1000);
        foreach($threads as &$thread) if ( $thread->isWaiting() ) {
          $wait = !$thread->receive($socket);
          break;
        }
      }
    }
  }
  fclose($server);
}

// stops
foreach($threads as $j) {
  $j->stop();
  $j->join();
}