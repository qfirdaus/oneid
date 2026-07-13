<?php
echo "TEST OK<br>";
echo "HTTPS: " . ($_SERVER['HTTPS'] ?? 'not set') . "<br>";
echo "URL: " . ($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
