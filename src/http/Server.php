<?php
namespace http;
require_once __DIR__ . '/Request.php';
require_once __DIR__ . '/Response.php';
defined('MAX_THREADS') or define('MAX_THREADS', 100);
defined('MAX_INVOKE') or define('MAX_INVOKE', 100);
defined('DEBUG') or define('DEBUG', false);
/**
 * Defines the server class
 */
class Server {
    public static $events;
    private $port;
    private $hostname;
    private $app;
    private $socket;
    private $requests = array();
    private $threads = array();
    /**
     * Initialize a new HTTP server, yeah !
     */
    public function __construct($app) {
        $this->app = $app;
        $server = $this;
        register_shutdown_function(function() use($server) {
          $server->run();
        });
    }
    
    /** @trick **/
    public function run() {
        $this->threads = array();
        for($i = 0; $i < MAX_THREADS; $i++) {
            $this->threads[] = new ThreadRunner();
        }
        $dsn = 'tcp://'.$this->hostname.':'.$this->port;
        $this->socket = stream_socket_server($dsn);
        if ( !$this->socket ) {
            throw new \Exception(
                "Could not start server at $dsn\nCaused by : $errstr\n"
            );
        }
        echo "(i) Starting the server ($dsn)...\n";
        while(true) {
            if(($client = stream_socket_accept($this->socket)) !== false) {
                $this->accept($client);
            }
            usleep(1000);
        }
    }
    /**
     * Starts to listen
     */
    public function listen($port = '80', $hostname = '0.0.0.0') {
        $this->port = $port;
        $this->hostname = $hostname;
        return $this;
    }
    /**
     * Closing all connections
     */
    public function close() {
        echo "(i) Closing the server...\n";
        stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
        $this->socket = null;
        event_free($this->event);
        event_del($this->event);
        $this->event = null;
    }
    /**
     * Intercepts a new request
     */
    private function accept($socket) {
        if (DEBUG) echo "(debug) Receive request\n";
        $try = 0;
        while($try < 200) {
            foreach($this->threads as $id => &$thread) {
                if ( $thread->isWaiting() ) {
                    if (DEBUG) echo "(debug) Starting\n";
                    if ( isset($this->requests[$id]) ) {
                        unset($this->requests[$id]);
                    }
                    $this->requests[$id] = $socket;
                    $thread->synchronized(function($job, $app, $socket) {
                        $job->app = $app;
                        $job->socket = $socket;
                        $job->notify();
                    }, $thread, $this->app, $socket);
                    return true;
                } elseif( !$thread->isRunning() ) {
                    unset($this->requests[$id]);
                    unset($this->threads[$id]);
                    $this->threads[$id] =  new ThreadRunner();
                    /*if (DEBUG) {
                      echo "(debug) Reload thread\n";*/
                      echo '>> MAIN MEMORY   : ' . memory_get_usage(true) . " @ $id\n";
                    // }
                }
            }
            $try ++;
            usleep(10000); // wait 10ms (threads are all working)
        }
        // request is timed out (2 sec)
        if (DEBUG) {
          echo '> Request timeout' . "\n";
        }
        $request = new Request($socket);
        $request->reply(
           "HTTP/1.0 500 Internal Server Error\r\n"
           . "X-Reason: Server is busy\n"
           . "Connection: close\n\n"
        )->close();
        unset($request);
        unset($socket);
        return false;
    }
}

class ThreadRunner extends \Thread
{
    public $app;
    public $socket;
    public $invoke;
    
    public function __construct() {
        $this->start();
    }
    /**
     * The main thread function
     * @return void
     */
    public function run() {
        while($this->invoke < MAX_INVOKE) {
            $this->wait();
            if (DEBUG) echo "(debug) Job is loaded\n";
            if(is_resource($this->socket)) {
                if (DEBUG) echo "(debug) Processing\n";
                try {
                    $request = new Request($this->socket);
                    $response = new Response($request);
                    $this->app->request($request, $response);
                } catch(\Exception $ex) {
                    echo "> $ex\n";
                }
                if (DEBUG) echo "(debug) Finished\n";
                if ( !empty($this->socket) ) {
                  stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
                  unset($this->socket);
                }
                unset($request);
                unset($response);
            }
            $this->invoke++;
            usleep(1000);
        }
        echo '>> THREAD MEMORY : ' . memory_get_usage(true) . " @ ".$this->getThreadId()."\n";
    }
}