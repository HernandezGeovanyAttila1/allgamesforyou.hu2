<?php
// quick debug page to view session + cookies
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
?>
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Teszt</title></head><body>
<h1>Debug</h1>
<p>Session:</p>
<pre><?php var_export($_SESSION); ?></pre>
<p>Cookies:</p>
<pre><?php var_export($_COOKIE); ?></pre>
<p><a href="index.php">Back</a></p>
</body></html>
