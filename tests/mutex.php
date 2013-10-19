<?php

class Task extends Thread {
    public $var;
    public $lock;
    public function run() {
        var_dump($this);
        Mutex::lock($this->lock);
        var_dump($this);
        $this->var = 567;
        Mutex::unlock($this->lock);
    }
}
$lock = Mutex::create();
$t = new Task();
$t->lock = $lock;
$t->var = 123;
Mutex::lock($lock);
$t->start();
$t->var = 321;
Mutex::unlock($lock);
//$t->notify();
$t->join();
var_dump($t);
Mutex::destroy($lock);