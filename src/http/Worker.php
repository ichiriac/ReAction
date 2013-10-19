<?php
namespace react\http;
use react\console;
use react\process;

class Worker extends \Thread {
    public $app;
    public $request;
    public $invoke;
    /**
     * Initialize the worker
     */
    public function __construct() {
        $this->start();
    }
    /**
     * The main thread function
     * @return void
     */
    public function run() {
        return $this->synchronized(function($worker) {
            $worker->wait();
            process::start();
            while($this->invoke < process::$env->max_invoke) {
                console::trace('worker@' . $this->getThreadId() . ' is ready');
                $this->synchronized(function($worker) {
                    $worker->wait();
                    if($worker->request) {
                        console::trace("job process...");
                        try {
                            $worker->app->request(
                                $worker->request
                                , new Response($worker->request)
                            );
                        } catch(\Exception $ex) {
                            console::warn($ex->__toString());
                        }
                        console::trace("job finished");
                    }
                    $worker->invoke++;
                }, $this);
                usleep(1000);
            }
            console::debug(
                '>> THREAD MEMORY : ' . memory_get_usage(true) . " @ ".$this->getThreadId()
            );
        }, $this);
    }
}