<?php
namespace http;

class Request {
    private $socket;
    private $buffer;
    private $state = 0;
    public $method;
    public $url;
    public $headers = array();
    public $get = array();
    public $post = array();
    public $cookies = array();
    public function __construct( $socket ) {
        $this->socket = $socket;
        stream_set_blocking($this->socket, 1);
        $this->_read();
    }
    private function _read() {
        $buffer = null;
        while ($read = fread($this->socket, 1024)) {
            $buffer .= $read;
            if ( strpos($buffer, "\r\n\r\n") !== false ) break;
        }
        if (DEBUG) echo "(debug) Receive data :\n-->>$buffer<<--\n\n";
        $data = explode("\r\n", $buffer);
        // reading the request header
        if ( empty($this->state) ) {
            $header = explode(' ', array_shift($data));
            $this->method = strtoupper($header[0]);
            $header[1] = explode('?', $header[1], 2);
            $this->url = $header[1][0];
            if (!empty($header[1][1]) )  {
                parse_str($header[1][1], $this->get);
            }
            $this->version = $header[2];
            $this->state = 1;
        }
        // reading headers
        if ( $this->state === 1 ) {
            foreach($data as $i => $h) {
                if ( empty($h) ) {
                    $this->state = 2;
                    break;
                }
                $h = explode(':', $h, 2);
                $this->headers[strtolower(trim($h[0]))] = $h[1];
            }
        }
        // reading cookies
        if ( $this->state === 2 ) {
            if ( !empty($this->headers['cookie']) ) {
                $cookies = $this->headers['cookie'];
                $size = strlen($cookies);
                $name = null;
                $value = null;
                $s = 0;
                for($i = 0; $i < $size; $i++) {
                    $c = $cookies[$i];
                    if ( $c === '=' ) {
                        $s = 1;
                        $value = null;
                        continue;
                    }
                    if ( $c === ';' || $c === ',') {
                        $s = 0;
                        $this->cookies[trim($name)] = $value;
                        $name = null;
                        continue;
                    }
                    if ( $s === 0 ) {
                        $name .= $c;
                    } else $value .= $c;
                }
            }
            $this->state = 3;
        }
        // reading the raw data
        if ( $this->state === 3 ) {
            if ( $this->method === 'GET' ) {
                return;
            }
            // @todo should implement POST & MIME
        }
    }
    public function reply($message) {
        if (DEBUG) echo "(debug) Sending :\n-->>$message<<--\n\n";
        stream_socket_sendto($this->socket, $message);
        return $this;
    }
    public function _error($buffer, $error) {
        echo "> Error : $error\n";
        $this->close();
    }
    public function close() {
        if ( !empty($this->socket) ) {
            if (DEBUG) echo "(debug) Free request\n";
            stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
            $this->socket = null;
        }
    }
}