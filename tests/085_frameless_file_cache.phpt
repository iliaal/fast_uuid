--TEST--
opcache file cache entries with frameless calls are not reused across extension sets
--EXTENSIONS--
fast_uuid
opcache
--SKIPIF--
<?php
if (PHP_VERSION_ID < 80400) die('skip frameless calls need PHP 8.4+');
if (!is_readable('/proc/self/maps') || !preg_match('#\s(/\S*/fast_uuid\.so)$#m', file_get_contents('/proc/self/maps'))) die('skip needs /proc/self/maps to locate the loaded .so');
?>
--FILE--
<?php
// The file cache stores FRAMELESS_ICALL opcodes with process-local handler
// indices. A process with a different extension set must not load them.
preg_match('#\s(/\S*/fast_uuid\.so)$#m', file_get_contents('/proc/self/maps'), $m);
$dir = __DIR__ . '/085_frameless_file_cache.d';
@mkdir("$dir/cache", 0777, true);
file_put_contents("$dir/app.php", "<?php\necho strlen(uuid_v4()), \"\\n\";\n");
file_put_contents("$dir/dl.php", "<?php\ndl('fast_uuid.so');\ninclude __DIR__ . '/app.php';\n");

function ids(): int {
    global $dir;
    return count(glob("$dir/cache/*", GLOB_ONLYDIR));
}

function run(array $args): void {
    global $dir;
    $opcache = ['-d', 'opcache.enable=1', '-d', 'opcache.enable_cli=1', '-d', "opcache.file_cache=$dir/cache",
        '-d', 'opcache.file_cache_only=1', '-d', 'opcache.file_update_protection=0'];
    if (PHP_VERSION_ID < 80500) {
        array_unshift($opcache, '-d', 'zend_extension=opcache');
    }
    $proc = proc_open(array_merge([getenv('TEST_PHP_EXECUTABLE'), '-n'], $opcache, $args),
        [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes);
    $out = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $status = proc_close($proc);
    echo preg_match('/Call to undefined function uuid_v4\(\)/', $out) ? "undefined uuid_v4()" : trim($out), " (exit $status)\n";
}

run(['-d', "extension={$m[1]}", "$dir/app.php"]);            // compiles and caches frameless opcodes
run(['-d', "extension={$m[1]}", "$dir/app.php"]);            // same extension set: cache hit
var_dump(ids() === 1);                                        // system id is stable across starts
run(["$dir/app.php"]);                                        // no fast_uuid
var_dump(ids() === 2);
run(['-d', 'enable_dl=1', '-d', 'extension_dir=' . dirname($m[1]), "$dir/dl.php"]);
?>
--CLEAN--
<?php
$dir = __DIR__ . '/085_frameless_file_cache.d';
if (is_dir($dir)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) { $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname()); }
    rmdir($dir);
}
?>
--EXPECT--
36 (exit 0)
36 (exit 0)
bool(true)
undefined uuid_v4() (exit 255)
bool(true)
36 (exit 0)
