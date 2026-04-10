<?php
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__));
foreach ($files as $file) {
    if ($file->getExtension() === 'php' && strpos($file->getPathname(), 'database.php') === false && strpos($file->getPathname(), 'fix_sessions.php') === false) {
        $content = file_get_contents($file->getRealPath());
        $new_content = preg_replace('/(?<!@)session_start\s*\(\)\s*;/i', 'if (session_status() === PHP_SESSION_NONE) { session_start(); }', $content);
        if ($new_content !== $content) {
            file_put_contents($file->getRealPath(), $new_content);
            echo 'Updated: ' . $file->getPathname() . "\n";
        }
    }
}
echo "Done.\n";
