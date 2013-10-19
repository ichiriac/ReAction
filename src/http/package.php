<?php
namespace react\http;
use react\process;
return array(
    'createServer' => function($handler) {
        return process::serve(
            new Server($handler)
        );
    }
);