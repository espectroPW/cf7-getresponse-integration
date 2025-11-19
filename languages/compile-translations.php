#!/usr/bin/env php
<?php
/**
 * Simple PO to MO compiler for CF7 GetResponse Integration
 *
 * This script compiles .po files to .mo format
 * Usage: php compile-translations.php
 *
 * @package CF7_GetResponse_Integration
 * @since 3.1.0
 */

// Change to languages directory
$dir = __DIR__;
chdir($dir);

echo "CF7 GetResponse Integration - Translation Compiler\n";
echo "==================================================\n\n";

// Find all .po files
$po_files = glob('*.po');

if (empty($po_files)) {
    echo "No .po files found in {$dir}\n";
    exit(1);
}

echo "Found " . count($po_files) . " translation file(s):\n";
foreach ($po_files as $po_file) {
    echo "  - {$po_file}\n";
}
echo "\n";

// Check if msgfmt is available
exec('msgfmt --version 2>&1', $output, $return_code);

if ($return_code !== 0) {
    echo "ERROR: msgfmt command not found!\n";
    echo "\n";
    echo "Please install gettext tools:\n";
    echo "  - Ubuntu/Debian: sudo apt-get install gettext\n";
    echo "  - macOS: brew install gettext\n";
    echo "  - Windows: Download from http://gnuwin32.sourceforge.net/packages/gettext.htm\n";
    echo "\n";
    echo "Alternative: Use Poedit or online tool (see README.md)\n";
    exit(1);
}

echo "Compiling translations...\n\n";

$success_count = 0;
$error_count = 0;

foreach ($po_files as $po_file) {
    $mo_file = str_replace('.po', '.mo', $po_file);

    echo "Compiling {$po_file} → {$mo_file}... ";

    $command = sprintf(
        'msgfmt %s -o %s 2>&1',
        escapeshellarg($po_file),
        escapeshellarg($mo_file)
    );

    exec($command, $output, $return_code);

    if ($return_code === 0 && file_exists($mo_file)) {
        echo "✓ OK\n";
        $success_count++;
    } else {
        echo "✗ FAILED\n";
        if (!empty($output)) {
            echo "  Error: " . implode("\n  ", $output) . "\n";
        }
        $error_count++;
    }
}

echo "\n";
echo "==================================================\n";
echo "Compilation complete!\n";
echo "  Success: {$success_count}\n";
echo "  Failed:  {$error_count}\n";
echo "\n";

if ($success_count > 0) {
    echo "✓ Translation files are ready to use!\n";
    echo "\nTo test:\n";
    echo "1. Go to WordPress → Settings → General\n";
    echo "2. Change Site Language\n";
    echo "3. Visit CF7 → GR settings page\n";
}

exit($error_count > 0 ? 1 : 0);
