<?php
namespace http;
require_once __DIR__ . '/Request.php';
require_once __DIR__ . '/Response.php';
defined('MAX_THREADS') or define('MAX_THREADS', 100);
defined('DEBUG') or define('DEBUG', false);
/**
 * Defines the server class
 */
class Server {
    public static $events;
    private $socket;
    private $event;
    private $threads = array();
    /**
     * Initialize a new HTTP server, yeah !
     */
    public function __construct($app) {
        for($i = 0; $i < MAX_THREADS; $i++) {
            $this->threads[] = new ThreadRunner($app);
        }
    }
    /**
     * Starts to listen
     */
    public function listen($port = '80', $hostname = '0.0.0.0') {
        if ( !empty($this->socket) || !empty($this->event) ) {
            throw new \LogicException(
                'The server is already running'
            );
        }
        $dsn = 'tcp://'.$hostname.':'.$port;
        $this->socket = stream_socket_server(
            $dsn,
            $errno, $errstr
        );
        if ( !$this->socket  ) {
            throw new \Exception(
                "Could not start server at $dsn\nCaused by : $errstr\n"
            );
        }
        echo "(i) Starting the server ($dsn)...\n";
        stream_set_blocking($this->socket, 0);
        $this->event =event_new();
        event_set(
            $this->event
            , $this->socket
            , EV_READ | EV_PERSIST
            , array($this, '_accept')
            , self::$events
        );
        event_base_set($this->event, self::$events);
        event_add($this->event);
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
    public function _accept() {
        if (DEBUG) echo "(debug) Receive request\n";
        $socket = stream_socket_accept($this->socket);
        $try = 0;
        while($try < 200) {
            foreach($this->threads as $thread) {
                if ( $thread->isWaiting() ) {
                    if ( $thread->receive($socket) ) {
                        return true;
                    }
                }
            }
            $try ++;
            usleep(10000); // wait 10ms (threads are all working)
        }
        // request is timed out (2 sec)
        $request = new Request($socket);
        $request->reply(
           "HTTP/1.0 500 Internal Server Error\r\n"
           . "X-Reason: Server is busy\n"
           . "Connection: close\n\n"
        )->close();
        unset($request);
        return false;
    }
}

/**
 * Thread helper for running the request with a clean way
 */
class ThreadRunner extends \Thread {
    public $socket;
    public $app;
    /**
     * Initialize the thread with a callback
     */
    public function __construct($app) {
        $this->app = $app;
        $this->start();
    }
    /**
     * The main thread function
     * @return void
     */
    public function run() {
        while(true) {
            if (DEBUG) echo "(debug) Thread is available\n";
            $this->wait();
            if($this->socket != null) {
                gc_enable();
                if (DEBUG) echo "(debug) Processing\n";
                try {
                    $request = new Request($this->socket);
                    $response = new Response($request);
                    $this->app->request($request, $response);
                } catch(\Exception $ex) {
                    echo "> $ex\n";
                }
                unset($request);
                unset($response);
                gc_collect_cycles();
            }
            usleep(1000);
        }
    }
    /**
     * Receives a new request
     * @return boolean
     */
    public function receive( $socket ) {
        if ( $this->isWaiting() ) {
            return $this->synchronized(function($thread, $socket){
                if (DEBUG) echo "(debug) Starting\n";
                $thread->socket = $socket;
                return $thread->notify();
            }, $this, $socket);
        } else {
            return false;
        }
    }
}

/**
 * Registering a non blocking events
 */
Server::$events = event_base_new();
register_shutdown_function(function() {
    event_base_loop(Server::$events);
});