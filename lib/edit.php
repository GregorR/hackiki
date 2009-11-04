<?PHP
function performEdit($fsdir, $args) {
    global $wiki_base;

    $fshr = implode("/", $args);
    $fshre = htmlentities($fshr);
    $fnam = $fsdir . "/" . $fshr;

    // prepare our output
    $html = "<html><head><title>Edit: $fshr</title></head><body>";

    // handle something
    if (is_dir($fnam)) {
        // just display the dir
        $html .= "<h1>$fshre</h1><ul>";
        foreach (scandir($fnam) as $dirf) {
            if ($dirf[0] == ".") continue;
            $dirfe = htmlentities($dirf);
            $html .= "<li><a href=\"$wiki_base/edit/$fshre/$dirfe\">$dirfe</a></li>";
        }
        $html .= "</ul>";

        $html .= "<form action=\"$wiki_base/edit\" method=\"get\">" .
                 "<input type=\"hidden\" name=\"arg1\" value=\"$fshre\" />" .
                 "Create new file: <input type=\"text\" name=\"arg2\" />" .
                 "<input type=\"submit\" value=\"Create\" />" .
                 "</form>";

    } else {
        if (isset($_REQUEST["cont"]) && isset($_REQUEST["mode"])) {
            // modify it
            @mkdir(dirname($fnam), 0777, true);
            file_put_contents($fnam, str_replace("\r", "", stripslashes($_REQUEST["cont"])));
            chmod($fnam, intval($_REQUEST["mode"], 8));
            $html .= "File " . htmlentities($fshr) . " modified.<hr/>";
        }
    
        // get the contents and mode
        if (file_exists($fnam)) {
            $fcont = file_get_contents($fnam);
            $statbuf = stat($fnam);
            $mode = $statbuf["mode"] & 0777;
        } else {
            $fcont = "";
            $mode = 0755;
        }
    
        $html .= "<form action=\"$wiki_base/edit/" . htmlentities($fshr) . "\" method=\"post\">" .
                 "<h1>" . htmlentities($fshr) . "</h1>" .
                 "<textarea name=\"cont\" rows=\"25\" cols=\"80\">" .
                 htmlentities($fcont) .
                 "</textarea><br/>" .
                 "Mode: <input type=\"text\" name=\"mode\" value=\"" . sprintf("%o", $mode) . "\" /><br/>" .
                 "<input type=\"submit\" /></form>";
    }

    // useful links
    if (dirname($fshr) == "bin") {
        $html .= "<a href=\"$wiki_base/" . htmlentities(basename($fshr)) . "\">View this page</a><br/>";
    }
    $html .= "<a href=\"$wiki_base\">Wiki index</a><br/>";

    $html .= "</body></html>";
    return $html;
}
?>