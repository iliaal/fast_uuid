--TEST--
A dl()-loaded copy survives repeated requests (no stale frameless binding)
--EXTENSIONS--
fast_uuid
--SKIPIF--
<?php
if (!getenv('TEST_PHP_CGI_EXECUTABLE') || !is_executable(getenv('TEST_PHP_CGI_EXECUTABLE'))) die('skip php-cgi not available');
if (!is_readable('/proc/self/maps') || !preg_match('#\s(/\S*/fast_uuid\.so)$#m', file_get_contents('/proc/self/maps'))) die('skip needs /proc/self/maps to locate the loaded .so');
?>
--FILE--
<?php
// php-cgi -T runs the script once per request, so each dl() maps the .so
// again after the previous request unloaded it.
preg_match('#\s(/\S*/fast_uuid\.so)$#m', file_get_contents('/proc/self/maps'), $m);
$dir = __DIR__ . '/083_frameless_dl.d';
@mkdir($dir);
file_put_contents("$dir/main.php", <<<'PHP'
<?php
dl('fast_uuid.so');
set_error_handler(function (int $no, string $msg): bool { echo "E: $msg\n"; return true; });
include __DIR__ . '/inc.php';
PHP);
file_put_contents("$dir/inc.php", <<<'PHP'
<?php
namespace Fl;
echo strlen(uuid_v4()), "\n";
var_dump(uuid_is_valid(null));
try { uuid_v7_at([]); } catch (\TypeError $e) { echo $e->getMessage(), "\n"; }
try { uuid_to_bin('x'); } catch (\Exception $e) { echo get_class($e), ' in ', $e->getTrace()[0]['function'], "\n"; }
PHP);
// run-tests exports CGI variables (SCRIPT_FILENAME points at this test); drop
// them so php-cgi runs main.php instead of re-running the test.
$env = array_diff_key(getenv(), array_flip(['SCRIPT_FILENAME', 'PATH_TRANSLATED', 'REQUEST_METHOD',
    'QUERY_STRING', 'CONTENT_TYPE', 'CONTENT_LENGTH', 'REDIRECT_STATUS', 'HTTP_COOKIE']));
$cmd = [getenv('TEST_PHP_CGI_EXECUTABLE'), '-n', '-q', '-d', 'enable_dl=1',
        '-d', 'extension_dir=' . dirname($m[1]), '-T', '3', "$dir/main.php"];
$proc = proc_open($cmd, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, null, $env);
$out = stream_get_contents($pipes[1]);
fclose($pipes[1]);
echo preg_replace('/^\s*Elapsed time: .*\R?/m', '', $out);
echo 'exit ', proc_close($proc), "\n";
?>
--CLEAN--
<?php
$dir = __DIR__ . '/083_frameless_dl.d';
@unlink("$dir/main.php");
@unlink("$dir/inc.php");
@rmdir($dir);
?>
--EXPECT--
36
E: uuid_is_valid(): Passing null to parameter #1 ($uuid) of type string is deprecated
bool(false)
uuid_v7_at(): Argument #1 ($unixMillis) must be of type int, array given
FastUuid\Exception\InvalidUuidStringException in uuid_to_bin
36
E: uuid_is_valid(): Passing null to parameter #1 ($uuid) of type string is deprecated
bool(false)
uuid_v7_at(): Argument #1 ($unixMillis) must be of type int, array given
FastUuid\Exception\InvalidUuidStringException in uuid_to_bin
36
E: uuid_is_valid(): Passing null to parameter #1 ($uuid) of type string is deprecated
bool(false)
uuid_v7_at(): Argument #1 ($unixMillis) must be of type int, array given
FastUuid\Exception\InvalidUuidStringException in uuid_to_bin
exit 0
