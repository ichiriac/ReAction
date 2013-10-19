<?php
abstract class Message extends Thread {
    public $data = array();
    public function send($data) {
        $this->lock();
        $this->data[] = $data;
        $this->unlock();
        echo "new $data\n";
        return $this->synchronized(function($thread) {
            return $thread->notify();
        }, $this);
    }
    public function consume() {
        var_dump($this->data);
        while(empty($this->data)) {
            $this->wait();
            usleep(1000);
        }
        var_dump($this->data);
        echo "ready\n";
        $this->lock();
        $item = array_shift($this->data);
        $this->unlock();
        return $item;
    }
}

class Dispatcher extends Message {
    public function run() {
        for($i = 0; $i < 10; $i++) {
            $this->send(
                ($i % 3) + 1
            );
        }
    }
}

class Consumer extends Thread {
    public $data;
    public function __construct() {
        $this->start();
    }
    public function run() {
        $this->synchronized(function($thread) {
            $thread->wait();
            echo "sleep " . $thread->data . "\n";
            sleep($thread->data);
            echo "wake up !\n";
        }, $this);
    }
}

$dispatcher = new Dispatcher();
$services = array();
for($i = 0; $i < 10; $i++) {
    $services[] = new Consumer();
}
$dispatcher->start();
for($i = 0; $i < 10; $i++) {

    $services[$i]->data = $dispatcher->consume();
    var_dump($services[$i]->data);
    $services[$i]->notify();
}
$dispatcher->join();