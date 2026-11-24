<?php
require __DIR__ . "/../vendor/autoload.php";
$root = dirname(__DIR__);
$parser = (new PhpParser\ParserFactory())->createForVersion(
    PhpParser\PhpVersion::fromString("7.4"),
);
$count = 0;
$failed = 0;
foreach (
    ["app", "config", "database", "routes", "tests", "scripts", "storage/framework/views"]
    as $dir
) {
    foreach (
        new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root . "/" . $dir, FilesystemIterator::SKIP_DOTS),
        )
        as $file
    ) {
        if ($file->getExtension() !== "php") {
            continue;
        }
        try {
            $parser->parse(file_get_contents($file->getPathname()));
            $count++;
        } catch (PhpParser\Error $e) {
            $failed++;
            echo $file->getPathname() . ": " . $e->getMessage() . PHP_EOL;
        }
    }
}
echo "PHP 7.4 grammar: $count files parsed, $failed failures. This does not replace execution on PHP 7.4.30.\n";
exit($failed ? 1 : 0);
