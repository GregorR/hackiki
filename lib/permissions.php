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

function getPermissions() {
    global $permissions, $enable_openid;

    if (isset($enable_openid) && $enable_openid && file_exists("etc/permissions")) {
        $permissions = array();
        $plines = explode("\n", file_get_contents("etc/permissions"));
        foreach ($plines as $line) {
            $permissions[] = explode(" ", $line);
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

    foreach ($touchedFiles as $file) {
        if ($file == "") continue;
        $filePerm = true;

        // go through the permissions, applying any matching one
        foreach ($permissions as $permission) {
            if ($permission[0] == "group") {
                $group = array_slice($permission, 2);
                // maybe add them to this group
                foreach ($user as $u) {
                    if (in_array($u, $group)) {
                        $user[] = $permission[1];
                    }
                }
    
            } else if ($permission[0] == "file") {
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
            }
        }

        $hasPermission = $hasPermission && $filePerm;
    }

    return $hasPermission;
}
?>
