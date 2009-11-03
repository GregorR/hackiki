<?PHP
function performEdit($fsdir, $args) {
    if (!isset($args[0])) {
        return "Use: edit/<file>";
    }
    $fshr = $args[0];

    // open the file
    $fnam = realpath($fsdir . "/bin/" . $fshr);
    if (substr($fnam, 0, strlen($fsdir)) != $fsdir) {
        return "Error: Trying to edit a file outside the filesystem.";
    }

    // make sure it's not in .hg
    if (strpos($fnam, "/.hg/") !== false) {
        return "Error: Cannot edit .hg directories.";
    }

    // now open it
    $html = "<html><head><title>Edit: $fshr</title></head><body>";
    if (file_exists($fnam)) {
        $fcont = file_get_contents($fnam);
    } else {
        $fcont = "";
    }
    $html .= "<form action=\"$wiki_base/edit/" . htmlentities($fshr) . "\" method=\"post\">" .
             "<textarea rows=\"25\" cols=\"80\">" .
             htmlentities($fcont) .
             "</textarea></form></body></html>";
    return $html;
}
?>
