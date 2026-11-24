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
            $source = file_get_contents($file->getPathname());
            // PHP-Parser's target version is best-effort, not a strict syntax gate.
            $newLine = null;
            foreach (token_get_all($source) as $token) {
                if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                if ($newLine !== null && $token === "(") {
                    throw new PhpParser\Error(
                        "Parenthesized dynamic class construction requires PHP 8.0; assign the class name to a variable first",
                        ["startLine" => $newLine]
                    );
                }
                $newLine = is_array($token) && $token[0] === T_NEW ? $token[2] : null;
            }
            $parser->parse($source);
            $count++;
        } catch (PhpParser\Error $e) {
            $failed++;
            echo $file->getPathname() . ": " . $e->getMessage() . PHP_EOL;
        }
    }
}
echo "PHP 7.4 best-effort syntax scan: $count files parsed, $failed failures. Includes a guard for parenthesized dynamic construction; does not replace PHP 7.4 lint/runtime testing.\n";
exit($failed ? 1 : 0);
