<?php



require_once ABSPATH . 'vendor/autoload.php';

// Get the $app var to use below
if (empty($app)) {
    $app = Flight::app();
}

require_once ABSPATH . 'app/config/config.php';

require_once ABSPATH . 'app/config/routes.php';

// Iniciar o servidor
$app->start();
