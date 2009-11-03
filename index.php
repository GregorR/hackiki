<?PHP
require_once("lib/config.php");
require_once("lib/polarun.php");

$pi = $_SERVER["PATH_INFO"];

// clone the fs
$fsf = tempnam("/tmp", "hackikifs.");
$fsdir = $fsf . ".d";
system("hg clone $hackiki_fs_path $fsdir");
unlink($fsf);

print "";
passthru("find $fsdir");

system("rm -rf $fsdir");
?>
