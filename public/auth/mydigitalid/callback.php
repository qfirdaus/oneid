<?php

require dirname(__DIR__, 3) . '/app/Auth/MyDigitalId/MyDigitalIdCallbackEndpoint.php';

\OneId\App\Auth\MyDigitalId\MyDigitalIdCallbackEndpoint::run();
