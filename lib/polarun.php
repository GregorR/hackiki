<?PHP
/*
 * Copyright (C) 2009 Gregor Richards
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

require_once("lib/env.php");
require_once("lib/files.php");

function polanice($fsdir, $cmd) {
    global $hackiki_path;

    $POLA_OPTS = "";
    if (file_exists("/lib64")) {
        $POLA_OPTS="$POLA_OPTS -f=/lib64";
    }
    if (file_exists("/etc/alternatives")) {
        $POLA_OPTS="$POLA_OPTS -f=/etc/alternatives";
    }
    if (file_exists("/proc/stat")) {
        $POLA_OPTS="$POLA_OPTS -f=/proc/stat";
    }

    $tmpf = tempnam("/tmp", "tmpdir.");
    $tmpd = $tmpf . ".d";
    mkdir($tmpd);
    unlink($tmpf);

    $oldpath = $_ENV["PATH"];
    putenv("PATH=/hackiki/bin:/hackiki/usr/bin:/bin:/usr/bin");
    cleanenv();
    handleFiles($tmpd);

    // read the output
    $cmdh = popen(
        "/usr/bin/pola-run -B $POLA_OPTS -tw /tmp $tmpd -tw /hackiki $fsdir " .
        "--cwd / " .
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
