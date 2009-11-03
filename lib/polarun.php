<?PHP
function polanice($fsdir, $cmd) {
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
    $_ENV["PATH"] = "/hackiki/bin:/bin:/usr/bin";

    // read the output
    $cmdh = popen("/usr/bin/pola-run -B $POLA_OPTS -f=/proc -tw /tmp $tmpd -tw /hackiki $fsdir " .
        "--prog=/usr/bin/nice -a=-n10 " .
        "-e $cmd", "r");
    $outp = "";
    if ($cmdh !== false) {
        while (!feof($cmdh)) {
            $outp .= fread($cmdh, 8192);
        }
    }
    $_ENV["PATH"] = $oldpath;

    system("rm -rf $tmpd");

    return $outp;
}
?>
