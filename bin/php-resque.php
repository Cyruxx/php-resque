<?php
$autoloadFiles = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php'];
foreach ($autoloadFiles as $autoloadFile) {
    if (file_exists($autoloadFile)) {
        require_once $autoloadFile;
    }
}

$application = new \ChrisBoulton\Resque\Console\Application('PHP-RESQUE CLI', '1.0.0');
$application->run();
