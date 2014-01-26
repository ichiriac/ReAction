<?php

class Job extends Thread {
    public $foo;
    public function run() {
        var_dump($this->foo); // foo
        usleep(10000);
        var_dump($this->foo); // bar
        usleep(10000);
        $this->foo = array('baz');
        usleep(10000);
        $this->foo = array('buz');
    }
}

$j= new Job();
$j->foo = array('foo');
$j->start();
$j->foo = array('bar');
usleep(25000);
var_dump($j->foo); // baz
$j->join();
var_dump($j->foo); // buz
