<?PHP
/*
 * Note that this is a potential rat's nest of mostly-innocuous race conditions
 * 
 * Copyright (C) 2010 Gregor Richards
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

// build a hash of the current request
function cacheHash() {
    global $env_ok;

    // build up a cache string that represents this request
    $cache = "";

    foreach ($env_ok as $ev) {
        $eva = getenv($ev);
        $cache .= $ev . "=" . base64_encode($eva) . "\n";
    }

    // turn that into a hash
    return sha1($cache);
}

// try to fetch an element for the current page in the cache
function fetchCache() {
    global $hackiki_path;

    // turn the request into a hash
    $hash = cacheHash();
    $cachedir = $hackiki_path . "/cache/";
    if (!file_exists($cachedir)) @mkdir($cachedir);

    // check if it's cached
    $hfile = $hackiki_path . "/cache/" . $hash . ".r";
    if (file_exists($hfile)) {
        // it's cached, check if it's out of date
        $cache = unserialize(gzuncompress(file_get_contents($hfile)));

        // first make sure all the dep files exist
        foreach ($cache["dependencies"] as $dep) {
            $depf = $cachedir . sha1($dep) . ".w";
            if (!file_exists($depf)) @touch($depf);
        }

        // then check them all
        foreach ($cache["dependencies"] as $dep) {
            $depf = $cachedir . sha1($dep) . ".w";
            if (!file_exists($depf) || filemtime($depf) >= filemtime($hfile)) {
                // nope, cache is old, ignore it!
                unlink($hfile);
                return false;
            }
        }

        // nope, still OK
        return $cache["output"];
    }

    return false;
}

// search for all files in a directory
function allFiles($dir) {
    if ($dir == "") {
        $dirc = scandir(".");
    } else {
        $dirc = scandir($dir);
        $dir .= "/";
    }
    $cont = array();
    foreach ($dirc as $file) {
        if ($file == "." || $file == ".." || file == ".hg") continue;
        $fn = $dir . $file;
        if (!is_link($fn) && is_dir($fn)) {
            // recurse
            $cont = array_merge($cont, allFiles($fn));
        } else {
            $cont[] = $fn;
        }
    }
    return $cont;
}

// figure out what file we touched
function cacheTouchedFiles() {
    $ret = array();
    $files = allFiles("");
    foreach ($files as $file) {
        if (fileatime($file) > 0) {
            $ret[] = $file;
        }
    }
    return $ret;
}

// save our output to the cache
function saveCache($outp, $touched_files) {
    global $hackiki_path;

    // get our cache
    $cachedir = $hackiki_path . "/cache/";
    if (!file_exists($cachedir)) @mkdir($cachedir);

    $cache = array("output" => $outp, "dependencies" => array());

    // turn the request into a hash
    $hash = cacheHash();
    $hfile = $cachedir . $hash . ".r";

    // mark each dependency
    foreach ($touched_files as $dep) {
        $cache["dependencies"][] = $dep;
    }

    // now write it to the cache
    @file_put_contents($hfile, gzcompress(serialize($cache)));
}

// save the fact that the given files were written to the cache
function saveCacheWrite($files) {
    global $hackiki_path;

    $cachedir = $hackiki_path . "/cache/";
    if (!file_exists($cachedir)) @mkdir($cachedir);

    foreach ($files as $file) {
        @touch($cachedir . sha1($file) . ".w");
    }
}

// clean out the cache
function cleanCache() {
    global $hackiki_path;

    $cachedir = $hackiki_path . "/cache/";
    $cleanf = $cachedir . "clean";
    $old = time() - 24 * 60 * 60;

    // check how long it's been
    if (file_exists($cleanf) && filemtime($cleanf) >= $old) {
        // hasn't been long enough
        return;
    }

    @touch($cleanf);

    // now look for old cache files
    $files = allFiles($cahedir);
    foreach ($files as $file) {
        if (substr($file, -2) != ".r") {
            // not a read cache, ignore it
            continue;
        }
        if (filemtime($file) < $old) {
            // old enouogh, get rid of it
            unlink($file);
        }
    }
}
