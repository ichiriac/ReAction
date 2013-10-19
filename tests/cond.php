<?php

class Job extends Thread {
    public $cond;
    public $mutex;
    public function run() {
        echo "wait\n";
        Mutex::lock($this->mutex);
        Cond::wait($this->cond, $this->mutex);
        echo "signal\n";
    }
}

$j = new Job();
$j->cond = Cond::create();
$j->mutex = Mutex::create();
$j->start();
sleep(1);
echo "finish to sleep\n";
Cond::signal($j->cond);
$j->join();
Cond::destroy($j->cond);
Mutex::unlock($j->mutex);
Mutex::destroy($j->mutex);
