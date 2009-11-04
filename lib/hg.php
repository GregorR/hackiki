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

function performHg($fsdir, $args) {
    global $wiki_base;

    // prepare our output
    $html = "<html><head><title>Hg</title></head><body>";

    // handle something
    rename("$fsdir.hg", ".hg");
    if (isset($_REQUEST["act"]) && isset($_REQUEST["rev"])) {
        $rev = escapeshellarg($_REQUEST["rev"]);
        $log = escapeshellarg("hg backout " . $_REQUEST["rev"]);
        $ph = false;
        if ($_REQUEST["act"] == "backout") {
            $ph = popen("hg backout -r $rev --merge -m $log < /dev/null", "r");
        } else if ($_REQUEST["act"] == "revert") {
            $ph = popen("hg revert --all -r $rev", "r");
        }
        if ($ph !== false) {
            $html .= "<pre>" . stream_get_contents($ph) . "</pre>";
            pclose($ph);
        }

        $html .= "<hr/>";
    }

    // now show the log
    $html .= "<pre>";
    $ph = popen("hg log -l 10", "r");
    if ($ph !== false) {
        $html .= stream_get_contents($ph);
        pclose($ph);
    }
    $html .= "</pre>";

    // and options
    $html .= "<hr/>" .
             "<form action=\"$wiki_base/hg\" method=\"get\">" .
             "<input type=\"text\" name=\"rev\" />" .
             "<input type=\"submit\" name=\"act\" value=\"revert\" />" .
             "<input type=\"submit\" name=\"act\" value=\"backout\" />" .
             "</form>";

    $html .= "</body></html>";
    rename(".hg", "$fsdir.hg");
    return $html;
}
?>
