<?php
require_once(__DIR__ . '/../src/react.php');
use react\console;
use react\process;

$http = react('http');
$port = process::$env->port;

class app extends react\app {
    public function request($req, $rep) {
        $rep->writeHead(
            200, array(
                'Content-Type' => 'text/plain'
            )
        );
        $rep->end('Hello World');
    }
}

$http
    ->createServer(new app())
    ->listen($port, '127.0.0.1')
;

console::info("Server is running at $port");
