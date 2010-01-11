<?PHP
/*
 * Copyright (C) 2009, 2010 Gregor Richards
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

require_once("lib/config.php");
require_once("lib/polarun.php");

if (isset($enable_openid) && $enable_openid) {
    require_once("lib/openid.php");
    require_once("lib/permissions.php");
    session_start();

    if (isset($_REQUEST["openidTryAuth"])) {
        openid_tryAuth($_REQUEST["login"], "openidFinishAuth");
        print "<html><head><title>Logging in...</title></head><body>Logging in...</body></html>";
        exit(0);

    } else if (isset($_REQUEST["openidFinishAuth"])) {
        $auth = openid_finishAuth();
        if ($auth["login"]) {
            $_SESSION["auth"] = $auth;

        } else {
            print "<html><head><title>Login failed</title></head><body>" .
                  "Login failed. <a href=\"" . htmlentities(openid_getReturnTo(false)) . "\">Continue</a>." .
                  "</body></html>";
            exit(0);

        }

    } else if (isset($_REQUEST["openidLogOff"])) {
        $_SESSION["auth"] = $auth = false;

    } else {
        if (!isset($_SESSION["auth"])) {
            $auth = false;
        } else {
            $auth = $_SESSION["auth"];
        }
    }

    // now put it in the environment
    if ($auth !== false) {
        $auth["short"] = openid_shortURL($auth);
        $auth["display"] = openid_humanName($auth, false);
        putenv("HACKIKI_AUTH_SHORT=" . $auth["short"]);
        putenv("HACKIKI_AUTH_OPENID=" . $auth["openid"]);
        putenv("HACKIKI_AUTH_NICKNAME=" . $auth["nickname"]);
        putenv("HACKIKI_AUTH_DISPLAY=" . $auth["display"]);
    } else {
        putenv("HACKIKI_AUTH_SHORT");
        putenv("HACKIKI_AUTH_OPENID");
        putenv("HACKIKI_AUTH_NICKNAME");
        putenv("HACKIKI_AUTH_DISPLAY");
    }
}
session_write_close();

$pi = $_SERVER["PATH_INFO"];
while ($pi[0] == "/") $pi = substr($pi, 1);
$log = $pi;

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
    $args[] = $curarg = stripslashes($_REQUEST["arg" . $i]);
    $cmd .= " " . escapeshellarg($curarg);
    $log .= " " . $curarg;
}

// move all the other requests into the environment
foreach ($_GET as $key => $val) {
    $val = stripslashes($val);
    putenv("REQUEST_$key=$val");
    $log .= " $key=$val";
}
foreach ($_POST as $key => $val) {
    $val = stripslashes($val);
    putenv("REQUEST_$key=$val");
    $log .= " $key=$val";
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

// get permissions if applicable
if (isset($enable_openid) && $enable_openid) {
    getPermissions();
}

// run the command
if ($majcmd == "edit") {
    require_once("lib/edit.php");
    $outp = performEdit($fsdir, $args);
} else if ($majcmd == "hg") {
    require_once("lib/hg.php");
    $outp = performHg($fsdir, $args);
} else if ($majcmd == "license") {
    require_once("lib/license.php");
    $outp = performLicense($fsdir, $args);
} else if ($majcmd == "login") {
    require_once("lib/login.php");
    $outp = performLogin($fsdir, $args);
} else {
    // see if we can run it
    if (file_exists("bin/" . $majcmd)) {
        $outp = polanice($fsdir, $cmd);
    } else {
        header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
        // make this a 404 message
        if (file_exists("bin/404")) {
            $outp = polanice($fsdir, "bin/404 " . $cmd);
        } else {
            $outp = "404: Page not found";
        }
    }
}

// if it's too big, cut it down
$maxsz = 10*1024*1024;
if (strlen($outp) > $maxsz) {
    $outp = substr($outp, 0, $maxsz);
}

// fix up .hg
if (file_exists(".hg")) die(".hg touched");
rename("$fsdir.hg", ".hg");

// if something was touched without permission, ignore this diff
if (isset($enable_openid) && $enable_openid) {
    $ph = popen("hg status | sed 's/^. //'", "r");
    if ($ph === false) die("Permission-checking failed, bailing out.");
    $status = stream_get_contents($ph);
    pclose($ph);
    if (!checkPermissions($majcmd, explode("\n", $status))) die("Permission denied.");
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
    posix_kill(posix_getpid(), SIGKILL);
}
ob_end_clean();
flush();
@fclose(STDIN);
@fclose(STDOUT);
@fclose(STDERR);
register_shutdown_function("shutdown");
if ($pid = pcntl_fork()) exit(0);

// now commit any changes
$user = "Hackiki";
if (isset($enable_openid) && $enable_openid) {
    if ($auth !== false) {
        $user = escapeshellarg($auth["display"]);
    } else {
        $user = "anonymous";
    }
}

// make 10 attempts
for ($i = 0; $i < 10; $i++) {
    exec("find . -name '*.orig' | xargs rm -f");
    exec("hg addremove");

    exec("hg commit -u $user -m " . escapeshellarg($log) . "");

    // try to push
    $output = array();
    $retval = 0;
    exec("hg push 2>&1", $output, $retval);
    if ($retval) {
        // failed, try to merge
        exec("hg heads --template='{node}\n'", $output);
        foreach ($output as $h) {
            exec("hg merge $h");
            exec("hg commit -m 'branch merge'");
            exec("hg revert --all");
            exec("find . -name '*.orig' | xargs rm -f");
        }
    } else {
        break;
    }
}

exec("rm -rf $fsdir $fsdir.hg");
?>
