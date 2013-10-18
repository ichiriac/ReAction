<?php

//define('DEBUG', true);
define('MAX_THREADS', 50);
require_once(__DIR__ . '/../src/http/Server.php');

class app {
    public function request($req, $rep) {
        $rep->writeHead(
            200, array(
                'Content-Type' => 'text/plain'
            )
        );
        $rep->end('Hello World');
    }
}
$server = new \http\Server(
    new app()
);
$server->listen(8001, '127.0.0.1');

echo "(i) Server is running at 8001\n";
