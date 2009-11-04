<?PHP
require_once("lib/env.php");

function polanice($fsdir, $cmd) {
    global $hackiki_path;

    $POLA_OPTS = "";
    if (file_exists("/lib64")) {
        $POLA_OPTS="$POLA_OPTS -f=/lib64";
    }
    if (file_exists("/etc/alternatives")) {
        $POLA_OPTS="$POLA_OPTS -f=/etc/alternatives";
    }

    $tmpf = tempnam("/tmp", "tmpdir.");
    $tmpd = $tmpf . ".d";
    mkdir($tmpd);
    unlink($tmpf);

    $oldpath = $_ENV["PATH"];
    putenv("PATH=$fsdir/bin:/bin:/usr/bin");
    putenv("HACKIKI_DIR=$fsdir");
    cleanenv();

    // read the output
    $cmdh = popen(
        "/usr/bin/pola-run -B $POLA_OPTS -f=/proc -tw /tmp $tmpd -fw=$fsdir " .
        "-f=$hackiki_path/limits --prog=$hackiki_path/limits " .
        "-fa=/usr/bin/nice -a=-n10 " .
        "-e $cmd 2>&1", "r");
    $outp = "";
    if ($cmdh !== false) {
        while (!feof($cmdh)) {
            $outp .= fread($cmdh, 8192);
        }
    }

    putenv("PATH=$oldpath");

    exec("rm -rf $tmpd");

    return $outp;
}
?>
