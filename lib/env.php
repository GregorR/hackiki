<?PHP
function cleanenv() {
    global $env_ok;
    foreach ($_ENV as $var => $val) {
        if (!in_array($var, $env_ok)) {
            putenv("$var");
        }
    }
}
?>
