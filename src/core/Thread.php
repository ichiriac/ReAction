<?php
namespace react\core;
use react\console;
use react\process;

abstract class Thread extends \Thread {
    public $app;
    public $path;
    public $mutex;
    public $cond;
    abstract public function close();
    abstract public function work();

    /**
     * Handle the thread start
     */
    final public function run() {
        if (!$this->synchronized(function($thread) {
            if (!$thread->wait()) return false;
            defined('REACT_PATH') or define('REACT_PATH', $thread->path);
            include(REACT_PATH . '/react.php');
            $thread->app->init();
            return true;
        }, $this)) {
            console::warn('Thread fail to wait');
        }
        console::trace('thread ' . $this->getThreadId() . ' is ready');
        $this->work();
        console::trace(
            'memory : ' . memory_get_usage(true) . " @ ".$this->getThreadId()
        );
    }
    /**
     * Initialize the current thread
     */
    final public function init($app) {
        console::trace('init the thread...');
        // starts the thread
        if (!$this->start(THREAD_CONTEXT)) {
            console::warn('Thread fail to start');
            return false;
        }
        // initialize the thread
        if (!$this->set(array(
            'app' => $app,
            'path' => REACT_PATH
        ))) {
            console::warn('Thread fail to sync');
            return false;
        }
        return true;
    }
    /**
     * Sets a list of properties and synchronize them with the thread
     * WARNING : The thread is supposed to wait them
     * @return boolean
     */
    final public function set($data) {
        return $this->synchronized(function($thread, $data) {
            foreach($data as $key => $value) {
                $thread->$key = $value;
            }
            return $thread->notify();
        }, $this, $data);
    }
}
