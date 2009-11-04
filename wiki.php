<?PHP
require_once("lib/config.php");
require_once("lib/polarun.php");

$pi = $_SERVER["PATH_INFO"];
while ($pi[0] == "/") $pi = substr($pi, 1);

// get directly included argument
$slpos = strpos($pi, "/");
$args = array();
if ($slpos !== false) {
    $majcmd = substr($pi, 0, $slpos);
    $args[] = $curarg = substr($pi, $slpos + 1);
    $cmd = escapeshellarg($majcmd) . " " . escapeshellarg($curarg);
} else {
    $majcmd = $pi;
    $cmd = escapeshellarg($majcmd);
}

// use a default command
if ($majcmd == "") {
    $majcmd = $cmd = "index";
}

// get the request arguments
for ($i = 1;; $i++) {
    if (!isset($_REQUEST["arg" . $i])) {
        break;
    }
    $args[] = $curarg = $_REQUEST["arg" . $i];
    $cmd .= " " . escapeshellarg($curarg);
}

// move all the other requests into the environment
foreach ($_REQUEST as $key => $val) {
    putenv("REQUEST_$key=$val");
}
putenv("HACKIKI_BASE=$wiki_base");

// clone the fs
$fsf = tempnam("/tmp", "hackikifs.");
$fsdir = $fsf . ".d";
exec("hg clone $hackiki_fs_path $fsdir");
unlink($fsf);

// and move .hg somewhere safe
rename("$fsdir/.hg", "$fsdir.hg");
chdir("$fsdir");

// run the command
if ($majcmd == "edit") {
    require_once("lib/edit.php");
    $outp = performEdit($fsdir, $args);
} else {
    $outp = polanice($fsdir, $cmd);
}

// handle headers
if (substr($outp, 0, 8) == "headers\n") {
    // it has headers, send them
    $outlines = explode("\n", $outp);
    for ($i = 1; isset($outlines[$i]); $i++) {
        $l = $outlines[$i];
        if ($l == "") {
            // done with headers
            break;
        }
        header($l);
    }

    // now recombine the output
    $outp = implode("\n", array_slice($outlines, $i+1));
}

print $outp;

// detach from the user
function shutdown() {
    posix_kill(posix_getpid(), SIGHUP);
}
ob_end_clean();
flush();
if ($pid = pcntl_fork()) exit(0);
@fclose(STDIN);
@fclose(STDOUT);
@fclose(STDERR);
register_shutdown_function("shutdown");
if ($pid = pcntl_fork()) exit(0);

// now commit any changes
if (!file_exists(".hg")) {
    rename("$fsdir.hg", ".hg");

    // make 10 attempts
    print "<pre>";
    for ($i = 0; $i < 10; $i++) {
        exec("find . -name '*.orig' | xargs rm -f");
        exec("hg addremove");
        exec("hg commit -m " . escapeshellarg($cmd) . "");

        // FIXME: merging
        exec("hg push 2>&1");
    }
    print "</pre>";
}

exec("rm -rf $fsdir $fsdir.hg < /dev/null >& /dev/null &");
?>
