<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$controller = new App\Controllers\AppController();
$controller->dispatch();
