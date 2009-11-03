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
    $_ENV["REQUEST_" . $key] = $val;
}
$_ENV["WIKI_BASE"] = $wiki_base;

// clone the fs
$fsf = tempnam("/tmp", "hackikifs.");
$fsdir = $fsf . ".d";
system("hg clone $hackiki_fs_path $fsdir >& /dev/null");
unlink($fsf);

// run the command
if ($majcmd == "edit") {
    print "Edit";
} else {
    $outp = polanice($fsdir, $cmd);

    // handle headers
    if (preg_match("/^headers\n/", $outp)) {
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
        $outp = implode("\n", array_slice($outlines, $i));
    }

    print $outp;
}

system("rm -rf $fsdir");
?>
