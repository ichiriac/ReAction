<?php

class Response extends Thread {
    public function run() {
        $this->wait = true;
        $context = new ZMQContext();
        $requests = new ZMQSocket($context, ZMQ::SOCKET_REP);
        $requests->connect('inproc://' . $this->getThreadId() ) ;
        echo "Client is ready\n";
        while($this->wait) {
            $message = $requests->recv();
            echo "receive>>$message<<\n";
            sleep(1);
            $requests->send(
                "HTTP/1.0 200 OK\r\nServer: Test\r\nConnection: close\r\nContent-Type: text/plain\r\n\r\nHello world"
            );
            echo "reply\n";
        }
    }
    public function stop() {
        $this->wait = false;
        return $this->join();
    }
}

$ctx = new ZMQContext();
$threads = array();
for($i = 0; $i < 10; $i++) {
    $t = new Response();
    $t->start();
    $threads[] = $t;
}
$server = new ZMQSocket($ctx, ZMQ::SOCKET_REP);
$server->bind("tcp://*:8001");
while (true) {
    $message = $server->recv();
    $reply = strrev($message);
    $server->send($reply);
}

