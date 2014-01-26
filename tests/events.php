<?php

/**
 * Handle messaging
 */
class MessageBuffer {

    const LOCK_WRITE    = 1;
    const LOCK_READ     = 2;
    const LOCK_CONSUME  = 4;
    private $ptr;

    /**
     * Init
     */
    public function __construct($id) {
        $this->ptr = shmop_open($id, 'c', 0644, 4095 + 2);
        if ( $this->ptr === false ) {
            throw new \Exception(
                'Unable to create a shared memory block'
            );
        }
    }
    public function __destruct() {
        if ( $this->ptr ) {
            shmop_delete($this->ptr);
            shmop_close($this->ptr);
            $this->ptr = null;
        }
    }
    /**
     * Returns the size and the state of the current buffer
     * $size => 0 .. 4095
     * $state => 0 .. 15 (binary)
     * - lock for write
     * - lock for read
     * - lock from consume
     */
    public function getSeal() {
        $seal = shmop_read($this->ptr, 0, 2);
    }
    public function setSeal($size, $flag) {
    }
    public function read() {
    }
    public function consume() {
    }
    public function write($data) {
        while(true) {
        }
        $seal = $this->getSeal();

        $seal = shmop_read($this->ptr, 0, 2);
    }
}

abstract class Message extends Thread {
    public $data;
    public function send($data) {
        $this->lock();
        $this->data[] = $data;
        $this->unlock();
        echo "new $data\n";
    }
    public function consume() {
        var_dump($this->data);
        while(empty($this->data)) {
            usleep(1000);
            echo "wait...\n";
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

$s = new MessageStack();
$s->start();
$s->send(123);
$s->stop();


/*
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
$dispatcher->join();*/