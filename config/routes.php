<?php
use Cake\Routing\Router;

Router::plugin('WRUtils', function ($routes) {
    $routes->fallbacks();
});
