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

global $openid_tmpDir;
$openid_tmpDir = "/tmp/php_openid";

function openid_displayError($message) {
    die($message);
}

function openid_doIncludes() {
    /**
     * Require the OpenID consumer code.
     */
    require_once "Auth/OpenID/Consumer.php";

    /**
     * Require the "file store" module, which we'll need to store
     * OpenID information.
     */
    require_once "Auth/OpenID/FileStore.php";

    /**
     * Require the Simple Registration extension API.
     */
    require_once "Auth/OpenID/SReg.php";

    /**
     * Require the PAPE extension module.
     */
    require_once "Auth/OpenID/PAPE.php";
}

openid_doIncludes();

function &openid_getStore() {
    global $openid_tmpDir;

    /**
     * This is where the example will store its OpenID information.
     * You should change this path if you want the example store to be
     * created elsewhere.  After you're done playing with the example
     * script, you'll have to remove this directory manually.
     */
    $store_path = $openid_tmpDir;

    if (!file_exists($store_path) &&
        !mkdir($store_path)) {
        print "Could not create the FileStore directory '$store_path'. ".
            " Please check the effective permissions.";
        exit(0);
    }

    return new Auth_OpenID_FileStore($store_path);
}

function &openid_getConsumer() {
    /**
     * Create a consumer object using the store object created
     * earlier.
     */
    $store = openid_getStore();
    $consumer =& new Auth_OpenID_Consumer($store);
    return $consumer;
}

function openid_getScheme() {
    $scheme = 'http';
    if (isset($_SERVER['HTTPS']) and $_SERVER['HTTPS'] == 'on') {
        $scheme .= 's';
    }
    return $scheme;
}

function openid_getReturnTo($append) {
    $request_uri = $_SERVER['REQUEST_URI'];
    $ruq = strpos($request_uri, "?");
    if ($ruq !== false) {
        $request_uri = substr($request_uri, 0, $ruq);
    }
    $url = sprintf("%s://%s:%s%s?",
                   openid_getScheme(), $_SERVER['SERVER_NAME'],
                   $_SERVER['SERVER_PORT'],
                   $request_uri);
    foreach ($_REQUEST as $rqkey => $rqval) {
        if (strpos($rqkey, "openid") !== 0 && $rqkey != "PHPSESSID") {
            $url .= urlencode($rqkey) . "=" . urlencode($rqval) . "&";
        }
    }
    if ($append !== false) {
        $url .= $append;
    }
    return $url;
}

function openid_getTrustRoot() {
    $dir = dirname($_SERVER['PHP_SELF']);
    if ($dir != "/") $dir = "/";
    return sprintf("%s://%s:%s%s",
                   openid_getScheme(), $_SERVER['SERVER_NAME'],
                   $_SERVER['SERVER_PORT'],
                   $dir);
}

function openid_tryAuth($openid, $append) {
    $consumer = openid_getConsumer();

    // Begin the OpenID authentication process.
    $auth_request = $consumer->begin($openid);

    // No auth request means we can't begin OpenID.
    if (!$auth_request) {
        openid_displayError("Authentication error; not a valid OpenID.");
    }

    $sreg_request = Auth_OpenID_SRegRequest::build(
                                     // Required
                                     array('nickname'),
                                     // Optional
                                     array('fullname', 'email'));

    if ($sreg_request) {
        $auth_request->addExtension($sreg_request);
    }

    // Redirect the user to the OpenID server for authentication.
    // Store the token for this authentication so we can verify the
    // response.

    // For OpenID 1, send a redirect.  For OpenID 2, use a Javascript
    // form to send a POST request to the server.
    if ($auth_request->shouldSendRedirect()) {
        $redirect_url = $auth_request->redirectURL(openid_getTrustRoot(),
                                                   openid_getReturnTo($append));

        // If the redirect URL can't be built, display an error
        // message.
        if (Auth_OpenID::isFailure($redirect_url)) {
            openid_displayError("Could not redirect to server: " . $redirect_url->message);
        } else {
            // Send redirect.
            header("Location: ".$redirect_url);
        }
    } else {
        // Generate form markup and render it.
        $form_id = 'openid_message';
        $form_html = $auth_request->htmlMarkup(openid_getTrustRoot(), openid_getReturnTo($append),
                                               false, array('id' => $form_id));

        // Display an error if the form markup couldn't be generated;
        // otherwise, render the HTML.
        if (Auth_OpenID::isFailure($form_html)) {
            openid_displayError("Could not redirect to server: " . $form_html->message);
        } else {
            print $form_html;
        }
    }
}

function openid_escape($thing) {
    return htmlentities($thing);
}

function openid_finishAuth() {
    $consumer = openid_getConsumer();

    // Complete the authentication process using the server's
    // response.
    $return_to = openid_getReturnTo(false);
    $response = $consumer->complete($return_to);

    // stuff to return
    $msg = "";
    $login = false;
    $openid = "";
    $nickname = "";
    $sreg = array();

    // Check the response status.
    if ($response->status == Auth_OpenID_CANCEL) {
        // This means the authentication was cancelled.
        $msg = 'Verification cancelled.';
        $login = false;
    } else if ($response->status == Auth_OpenID_FAILURE) {
        // Authentication failed; display the error message.
        $msg = "OpenID authentication failed: " . $response->message;
        $login = false;
    } else if ($response->status == Auth_OpenID_SUCCESS) {
        // This means the authentication succeeded; extract the
        // identity URL and Simple Registration data (if it was
        // returned).
        $openid = $response->getDisplayIdentifier();

        $msg = "Successfully verified.";
        $login = true;

        $sreg_resp = Auth_OpenID_SRegResponse::fromSuccessResponse($response);
        $sreg = $sreg_resp->contents();

        // nickname we care about in particular
        if (isset($sreg["nickname"])) {
            $nickname = $sreg["nickname"];
        } else if (isset($sreg["firstname"])) {
            $nickname = $sreg["firstname"];
        } else if (isset($sreg["fullname"])) {
            $nickname = $sreg["fullname"];
        } else {
            $nickname = $openid;
        }
    }

    return array("msg" => $msg, "login" => $login, "openid" => $openid, "nickname" => $nickname, "sreg" => $sreg);
}

function openid_shortURL($auth) {
    $ret = "";

    if ($auth["login"]) {
        $openidShort = $auth["openid"];

        // strip off http:// and /
        if (strpos($openidShort, "http://") === 0) {
            $openidShort = substr($openidShort, 7);
        }
        if (substr($openidShort, -1) == "/") {
            $openidShort = substr($openidShort, 0, -1);
        }

        $ret = $openidShort;
    }

    return $ret;
}

function openid_humanName($auth, $html) {
    $ret = "";

    if ($auth["login"]) {
        $openidShort = openid_shortURL($auth);

        if ($auth["openid"] != $auth["nickname"]) {
            $ret = $auth["nickname"] . " ";
            if ($html) $ret .= "<span style='font-size: 0.75em'>";
            $ret .= "(" . $openidShort . ")";
            if ($html) $ret .= "</span>";

        } else {
            $ret = $openidShort;

        }

    }

    return $ret;
}

?>
