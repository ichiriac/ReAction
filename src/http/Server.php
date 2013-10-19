<?php
namespace react\http;

use react\console;
use react\process;
use react\core\Thread;

/**
 * Defines the server class
 */
class Server extends Thread {
    public $port;
    public $hostname;
    public $app;
    public $ev;
    public $socket;
    public $socket_ev;
    public $requests;
    public $workers;

    /**
     * Initialize a new HTTP server, yeah !
     */
    public function __construct($app) {
        $this->app = $app;
    }

    /**
     * The main server loop
     */
    public function work() {
        $this->requests = new \SplObjectStorage();
        $dsn = 'tcp://'.$this->hostname.':'.$this->port;
        $this->socket = stream_socket_server($dsn, $errno, $errstr);
        if ( !$this->socket ) {
            throw new \Exception(
                "Could not start server at $dsn\nCause by : $errstr\n"
            );
        }
        console::debug('Listen at ' . $dsn);
        stream_set_blocking($this->socket, 0);
        $this->ev = event_base_new();
        $this->socket_ev =event_new();
        event_set(
            $this->socket_ev
            , $this->socket
            , EV_READ | EV_PERSIST
            , array($this, '_accept')
            , $this->ev
        );
        event_base_set($this->socket_ev,$this->ev);
        event_add($this->socket_ev);
        event_base_loop($this->ev);
    }
    /**
     * Prepare to listen
     */
    public function listen($port = '80', $hostname = '0.0.0.0') {
        $this->port = $port;
        $this->hostname = $hostname;
        $this->init($this->app);
        // initialize workers
        $this->workers = array();
        for($i = 0; $i < process::$env->threads; $i++) {
            $this->workers[$i] = new Worker();
        }
        // initialize the thread state
        foreach($this->workers as $w) {
            if ( !$this->workers[$i]->init($this->app) ) {
                console::error('Could not start thread');
            }
        }
        process::stop(0);
        return $this;
    }
    /**
     * Closing all connections
     */
    public function close() {
        $this->synchronized(function($thread) {
            console::log('Closing the server...');
            stream_socket_shutdown($thread->socket, STREAM_SHUT_RDWR);
            event_free($thread->socket_ev);
            event_del($thread->socket_ev);
            event_base_loopexit($thread->ev);
            event_base_free($thread->ev);
            unset(
                $thread->socket
                , $thread->socket_ev
                , $thread->ev
            );
        }, $this);
        return $this;
    }
    /**
     * The request is ready to be processed
     */
    public function _process( $client ) {
        $try = 0;
        while($try < 200) {
            foreach($this->workers as $id => $worker) {
                if ( $worker->isWaiting() ) {
                    $client->worker = $id;
                    $client->request->socket = $client->socket;
                    $worker->synchronized(function($job, $app, $request) {
                        $job->app = $app;
                        $job->request = $request;
                        $job->notify();
                    }, $worker, $this->app, $client->request);
                    return true;
                } elseif( !$worker->isRunning() ) {
                    unset($this->workers[$id]);
                    $this->workers[$id] = new Worker();
                    console::debug(
                        '>> MAIN MEMORY   : ' . memory_get_usage(true) . '@' . $id
                    );
                }
            }
            $try ++;
            usleep(10000); // wait 10ms (threads are all working)
        }
        // request is timed out (2 sec)
        console::warn('Request timeout');
        $client->request->reply(
           "HTTP/1.0 500 Internal Server Error\r\n"
           . "X-Reason: Server is busy\n"
           . "Connection: close\n\n"
        )->close();
        return false;
    }
    /**
     * Intercepts a new request
     */
    public function _accept() {
        $this->requests->attach(
            new Client($this)
        );
    }
}

