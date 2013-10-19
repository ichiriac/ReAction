<?php

class Job extends Thread {
    public $foo;
    public function run() {
        var_dump($this->foo); // foo
        usleep(10000);
        var_dump($this->foo); // bar
        usleep(10000);
        $this->foo = 'baz';
        usleep(10000);
        $this->foo = 'buz';
    }
}

$j= new Job();
$j->foo = 'foo';
$j->start();
$j->foo = 'bar';
usleep(20000);
var_dump($j->foo); // baz
$j->join();
var_dump($j->foo); // buz
