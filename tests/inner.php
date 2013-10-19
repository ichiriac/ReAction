<?php
// this code does not works
class Job extends Thread {
    public $inner = array();
    public function run() {
        foreach($this->inner as $i) $i->start();
        var_dump($this->inner);
        foreach($this->inner as $i) $i->join();
    }
}

class InnerJob extends Thread {
    public function run() {
        echo "sleep ...\n";
        sleep(1);
        echo "wakeup ...\n";
    }
}

$j = new Job();
$inner = array();
for($i = 0; $i < 10; $i++) {
    $inner[] = new InnerJob();
}
$j->start();
$j->join();