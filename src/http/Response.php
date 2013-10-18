<?php
namespace http;
defined('CRLF') or define('CRLF', "\r\n");
class Response {
    private $code = 200;
    private $status = 'OK';
    private $headers = array(
        'server' => 'Server: React',
        'connection' => 'Connection: close'
    );
    private $state = 0;
    private $request;
    public function __construct($request) {
        $this->request = $request;
    }
    public function writeHead($code, array $headers = null) {
        $this->code = $code;
        if (!empty($headers)) {
            foreach($headers as $key => $value) {
                $k = strtolower(trim($key));
                $this->headers[$k] = $key . ': ' . $value;
            }
        }
        return $this;
    }
    public function __get($var) {
        if ( $var === 'headersSent') {
            return $this->state > 0;
        } else throw \OutOfBoundsException(
            'Undefined property ' . $var
        );
    }
    public function sendHeaders() {
        if ( $this->state !== 0 ) {
            throw new \LogicException(
                'Headers already sent'
            );
        }
        $this->request->reply(
            'HTTP/1.1 ' . $this->code . ' ' . $this->status . CRLF
            . implode(CRLF, $this->headers)
            . CRLF . CRLF
        );
        $this->state = 1;
        return $this;
    }
    public function write($message) {
        if ( $this->state === 0) $this->sendHeaders();
        $this->request->reply($message);
        return $this;
    }
    public function end($message = null) {
        if (!empty($message)) $this->write($message);
        $this->request->close();
        return $this;
    }
}