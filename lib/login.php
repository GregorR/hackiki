<?PHP
/*
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

function performLogin($fsdir, $args) {
    global $wiki_base, $enable_openid, $auth;

    // prepare our output
    $html = "<html><head><title>Login</title></head><body>";

    // if currently logged in, present that
    if (!$enable_openid) {
        $html .= "This wiki does not support OpenID logins.";

    } else if ($auth !== false) {
        $html .= "You are logged in as " . $auth["display"] . "<br/>" .
                 "<a href=\"?openidLogOff\">Log off</a><br/>";

    } else {
        // otherwise, a login dialog
        $html .= "OpenID login:<br/>" .
                 "<form action=\"" . $wiki_base . "/login\" method=\"get\">" .
                 "<input type=\"hidden\" name=\"openidTryAuth\" />" .
                 "<input type=\"text\" name=\"login\" />" .
                 "<input type=\"submit\" value=\"Log in\" />" .
                 "</form>";

    }

    $html .= "<br/><a href=\"" . $wiki_base . "/\">Wiki index</a></body></html>";

    return $html;
}
?>
