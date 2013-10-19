<?php
namespace react\http;
return array(
    'createServer' => function($handler) {
        return new Server($handler);
    }
);