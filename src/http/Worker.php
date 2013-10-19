<?php
namespace react\http;
use react\console;
use react\process;
use react\core\Thread;

class Worker extends Thread {
    public $request;
    public $invoke;
    public $wait;
    /** closing the thread **/
    public function close() {
        $this->wait = false;
    }
    /**
     * The main thread function
     */
    public function work() {
        $this->wait = true;
        while($this->wait) {
            if ($this->synchronized(function($worker) {
                if (!$worker->wait()) return false;
                if($worker->request) {
                    console::trace("job process...");
                    try {
                        $worker->app->request(
                            $worker->request
                            , new Response($worker->request)
                        );
                    } catch(\Exception $ex) {
                        console::warn($ex->__toString());
                        return false;
                    }
                    console::trace("job finished");
                }
                return true;
            }, $this)) {
                $this->invoke++;
            }
            if ( $this->wait ) {
                $this->wait = $this->invoke < process::$env->max_invoke;
            }
            usleep(1000);
        }
    }
}