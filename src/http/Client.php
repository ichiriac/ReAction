<?php
namespace react\http;
use react\console;

class Client {
    public $socket;
    private $server;
    private $buffer;
    public $request;
    public $worker;
    public function __construct($server) {
        $this->socket = stream_socket_accept($server->socket);
        stream_set_blocking($this->socket, 0);
        $this->server = $server;
        console::trace('Receive a request ' . $this->socket);
        $this->buffer = event_buffer_new(
            $this->socket
            , array($this, '_read')
            , array($this, '_write')
            , array($this, '_error')
        );
        event_buffer_base_set($this->buffer, $this->server->ev);
        event_buffer_timeout_set($this->buffer, 30, 30);
        event_buffer_watermark_set($this->buffer, EV_READ, 0, 0xffffff);
        event_buffer_priority_set($this->buffer, 10);
        event_buffer_enable($this->buffer, EV_READ | EV_PERSIST);
        $this->request = new Request();
    }
    public function _error() {
        var_dump('BUFFER ERROR : ', func_get_args());
        $this->close();
    }
    public function _read() {
        $buffer = null;
        while ($read = event_buffer_read($this->buffer, 1024)) {
            if ( strpos($buffer, "\r\n\r\n") !== false ) break;
        }
        console::trace('READ : ' . $buffer);
        if ( $this->request->_read($buffer) ) {
            // the request is ready to be processed
            $this->server->_process($this);
        }
    }
    public function _write() {
    }
    public function close() {
        console::trace('Close the request ' . $this->socket);
        event_buffer_disable($this->buffer, EV_READ | EV_WRITE);
        event_buffer_free($this->buffer);
        stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
        unset($this->socket, $this->buffer);
        $this->server->requests->detach($this);
    }
}