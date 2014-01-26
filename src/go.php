<?php
namespace beaba {
    if ( !class_exists('react\\console', false) ) {
        class console {
            public static function log($msg) {
                echo '[log  ] ' . $msg . "\n";
            }
            public static function info($msg) {
                echo '[info ] ' . $msg . "\n";
            }
            public static function trace($msg) {
                echo '[trace] ' . $msg . "\n";
            }
            public static function warn($msg) {
                echo '[warn ] ' . $msg . "\n";
            }
            public static function error($msg) {
                echo '[error] ' . $msg . "\n";
            }
            public static function debug($msg) {
                echo '[debug] ' . $msg . "\n";
            }
        }
        class package {
            private $helpers;
            private $name;
            public function __construct(array $helpers, $name) {
                $this->helpers = $helpers;
                $this->name = $name;
            }
            public function __call($helper, $args) {
                if ( isset($this->helpers[$helper]) ) {
                    return call_user_func_array($this->helpers[$helper], $args);
                } else {
                    throw new \BadMethodCallException (
                        'Undefined helper ' . $this->name . '::' . $helper
                    );
                }
            }
        }
        class json implements \ArrayAccess {
            private $data;
            public function __construct($data) {
                if ( is_string($data)) {
                    $data = json_decode($data, true);
                }
                if ( $data instanceof json ) {
                    $data = $data->getData();
                }
                $this->data = $data;
            }
            public function merge($data) {
                $this->data = array_replace(
                    $this->data,
                    $data instanceof json ?
                    $data->getData() : $data
                );
            }
            public function getData() {
                return $this->data;
            }
            public function __get($key) {
                if (!isset($this->data[$key])) {
                    return null;
                }
                if(is_array($this->data[$key])) {
                    $this->data[$key] = new self($this->data[$key]);
                }
                return $this->data[$key];
            }
            public function __set($key, $value) {
                $this->data[$key] = $value;
            }
            public function __isset($key) {
                return isset($this->data[$key]);
            }
            public function offsetExists($offset) {
                return isset($this->data[$offset]);
            }
            public function offsetGet($offset) {
                return $this->__get($offset);
            }
            public function offsetSet($offset, $value) {
                $this->__set($offset, $value);
            }
            public function offsetUnset($offset) {
                unset($this->data[$offset]);
            }
        }
        class app {
            public function init() { }
            public function request($req, $res) { }
        }
        class process {
            public static $thread;
            public static $wait;
            public static $servers;
            public static $config;
            public static $env;
            public static function start() {
                self::$wait = true;
                /** light autoload system **/
                spl_autoload_register(function($class) {
                    if (substr_compare($class, 'react\\', 0, 6) === 0) {
                        require_once( REACT_PATH . '/' . strtr(substr($class, 6), '\\', '/') . '.php');
                        return true;
                    } else return false;
                }, true);
                /** catch errors **/
                $errorManager = function($error, $desc = null) {
                    if ( !empty($error) ) {
                        if ( $error instanceof \Exception) {
                            console::error($error->__toString());
                            process::stop(2);
                        } else {
                            console::error($desc);
                            debug_print_backtrace();
                        }
                    }
                    return true;
                };
                set_error_handler($errorManager, E_ALL);
                set_exception_handler($errorManager);
                /** loading the env conf **/
                if (empty(self::$env)) {
                    if (file_exists('configuration.json')) {
                        self::$config = new json(
                            file_get_contents('configuration.json')
                        );
                        self::$env = new json(self::$config->env->default);
                        if (!empty(self::$config->current)) {
                            self::$env->merge(
                                self::$config->env[self::$config->current]
                            );
                        }
                    }
                }
            }
            public static function serve($server) {
                self::$servers[] = $server;
                console::trace('Register ' . get_class($server));
                return $server;
            }
            public static function stop($code = 0) {
                if ( self::$thread ) {
                    self::$thread->close();
                } else {
                    if ( self::$wait ) {
                        self::$wait = false;
                    }
                    var_dump(self::$servers);
                    console::log('Program exits with code ' . $code);
                    exit($code);
                }
            }
        }
    }
}
namespace {
    use go\console;
    use react\process;
    /** modular functions **/
    if (!function_exists('react')) {
        function react($package) {
            static $packages = array();
            if ( !isset($packages[$package]) ) {
                $packages[$package] = new \react\package(
                    include(REACT_PATH . '/' . $package. '/package.php'),
                    $package
                );
            }
            return $packages[$package];
        }
    }
    /** main bootstrap **/
    defined('REACT_PATH') or  define('REACT_PATH', __DIR__);
    defined('THREAD_CONTEXT') or define(
        'THREAD_CONTEXT',
        PTHREADS_INHERIT_ALL
        // PTHREADS_INHERIT_CLASSES | PTHREADS_INHERIT_FUNCTIONS
    );
    if ( empty($this) ) {
        /** test compatibility **/
        if (!extension_loaded('libevent')) {
            console::error('The [libevent] extension is *REQUIRED*');
            process::stop(1);
        }
        if (!extension_loaded('pthreads')) {
            console::error('The [pthreads] extension is *REQUIRED*');
            process::stop(1);
        }
        console::debug('Starting the main process');
        /** run servers **/
        register_shutdown_function(function() {
            if ( process::$wait ) {
                console::debug('Starting the server');
                while(process::$wait) {
                    foreach(process::$servers as $id => $server) {
                        if ($server && !$server->isRunning()) {
                            //$server->join();
                            console::trace('server ' .$id . ' is closed');
                            unset(process::$servers[$id]);
                        }
                    }
                    if (empty(process::$servers)) break;
                    sleep(1);
                    console::trace('main loop');
                }
            }
            console::info('Stopping threads...');
            foreach(process::$servers as $server) {
                if ( $server ) {
                    $server->close();
                    $server->join();
                }
            }
        });
    } else {
        process::$thread = $this;
        var_dump(process::$servers);
        console::trace('init thread ' . $this->getThreadId());
    }
    process::start();

}