<?php

require dirname(__DIR__, 3) . '/app/Auth/MyDigitalId/MyDigitalIdLoginEndpoint.php';

\OneId\App\Auth\MyDigitalId\MyDigitalIdLoginEndpoint::run();
