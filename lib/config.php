<?PHP
$hackiki_path = "/var/lib/hackiki";
$wiki_base = "/wiki";
$env_ok = array("PATH", "HACKIKI_BASE");
$enable_openid = false;
$openid_administrator = "codu.org";

$hackiki_fs_path = "$hackiki_path/fs";
if ($enable_openid) {
    $permissions = array(
        array("group", "administrators", $openid_administrator),
        array("file", "etc/permissions", "all", "r"),
        array("file", "etc/permissions", "administrators", "w")
    );
    $env_ok[] = "HACKIKI_AUTH_SHORT";
    $env_ok[] = "HACKIKI_AUTH_OPENID";
    $env_ok[] = "HACKIKI_AUTH_NICKNAME";
    $env_ok[] = "HACKIKI_AUTH_DISPLAY";
}

$content_license = <<<LICENSE
Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
LICENSE;
?>
