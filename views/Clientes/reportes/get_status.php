<?php
session_start();

if (!array_key_exists('progress', $_SESSION)) {
    $_SESSION['progress'] = 0;
}

$_SESSION['progress'] += rand(5, 20);

echo $_SESSION['progress'] < 100 ? $_SESSION['progress'] : 100;