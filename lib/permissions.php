<?php
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

function smartExplode($by, $ln) {
    $part = $ln;
    $ret = array();
    $start = 0;
    while (($at = strpos($part, $by, $start)) !== false) {
        if (substr($part, $at-1, 1) == "\\") {
            $start = $at + 1;
        } else {
            $ret[] = substr($part, 0, $at);
            $part = substr($part, $at + 1);
            $start = 0;
        }
    }
    $ret[] = $part;
    return $ret;
}

function getPermissions() {
    global $permissions, $enable_openid;

    if (isset($enable_openid) && $enable_openid && file_exists("etc/permissions")) {
        $permissions = array();
        $plines = explode("\n", file_get_contents("etc/permissions"));
        foreach ($plines as $line) {
            $permissions[] = smartExplode(" ", $line);
        }
    }
}

function checkPermissions($touchedFiles) {
    global $permissions, $auth;

    $hasPermission = true;

    // get the user
    $user = array("all");
    if ($auth !== false) {
        $user[] = $auth["short"];
    } else {
        $user[] = "anonymous";
    }
    foreach ($permissions as $permission) {
        if ($permission[0] == "group") {
            $group = array_slice($permission, 2);
            // maybe add them to this group
            foreach ($user as $u) {
                if (in_array($u, $group)) {
                    $user[] = $permission[1];
                }
            }
        }
    }


    foreach ($touchedFiles as $file) {
        if ($file == "") continue;
        $filePerm = true;
        $scriptPerm = true;

        // see if we should check it as a script
        $chebang = false;
        if (file_exists($file)) {
            $stat = stat($file);
            $mode = $stat["mode"];
            if (($mode & 01) || ($mode & 001) || ($mode & 0001)) {
                // it's executable, get the chebang
                $fh = fopen($file, "r");
                if ($fh === false) return false;
                $ln = fgets($fh);
                fclose($fh);

                // check if it's a chebang line
                if (substr($ln, 0, 2) == "#!") {
                    $chebang = substr($ln, 2);
                } else {
                    $chebang = "none";
                }
            }
        }

        // go through the permissions, applying any matching one
        foreach ($permissions as $permission) {
            if ($permission[0] == "file") {
                if (in_array($permission[2], $user)) {
                    // check if this file matches
                    if (preg_match("#^" . $permission[1] . "$#", $file)) {
                        if (strpos($permission[3], "w") !== false) {
                            $filePerm = true;
                        } else {
                            $filePerm = false;
                        }
                    }
                }

            } else if ($permission[0] == "script" && $chebang !== false) {
                if (in_array($permission[2], $user)) {
                    // check if the chebang matches
                    if (preg_match("#^" . $permission[1] . "$#", $chebang)) {
                        if (strpos($permission[3], "w") !== false) {
                            $scriptPerm = true;
                        } else {
                            $scriptPerm = false;
                        }
                    }
                }

            }
        }

        $hasPermission = $hasPermission && $filePerm && $scriptPerm;
    }

    return $hasPermission;
}
?>
