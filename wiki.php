<?PHP
exit(0);
require_once("lib/config.php");
require_once("lib/polarun.php");

$pi = $_SERVER["PATH_INFO"];
while ($pi[0] == "/") $pi = substr($pi, 1);

// clone the fs
$fsf = tempnam("/tmp", "hackikifs.");
$fsdir = $fsf . ".d";
system("hg clone $hackiki_fs_path $fsdir >& /dev/null");
unlink($fsf);

// run the command
polanice($fsdir, $pi);

system("rm -rf $fsdir");
?>
