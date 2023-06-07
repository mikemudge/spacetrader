<?php

spl_autoload_register(function($class_name) {
    $path = "classes" . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $class_name).'.php';
    if (file_exists($path)) {
        require $path;
        return true;
    }
    echo("Path $path not found for $class_name\n");
    return false;
});