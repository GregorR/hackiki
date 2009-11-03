<?PHP
function polanice($fsdir, $cmd) {
    $POLA_OPTS = ""
    if (file_exists("/lib64")) {
        $POLA_OPTS="$POLA_OPTS -f=/lib64"
    }
    if (file_exists("/etc/alternatives")) {
        $POLA_OPTS="$POLA_OPTS -f=/etc/alternatives"
    }

    $tmpf = tempnam("/tmp", "tmpdir.");
    $tmpd = $tmpf . ".d";
    mkdir($tmpd);
    unlink($tmpf);

    passthru("/usr/bin/pola-run -B $POLA_OPTS -f=/proc -tw /tmp $tmpd -tw /hackiki $fsdir \
        --prog=/usr/bin/nice -a=-n10 \
        -e /hackiki/bin$cmd");

    system("rm -rf $tmpd");
}
?>
