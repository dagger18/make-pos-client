#!/usr/bin/env php
<?php
/**
 * Extract translatable strings from PHP source and write msgid entries to .po files.
 * Uses Symfony's translation:extract to find strings, then appends new ones to .po files.
 *
 * Usage: php bin/i18n-extract.php
 */

$langs = ['zh', 'vi', 'ja', 'ko', 'de', 'es', 'ar'];
$domain = 'messages';
$translationsDir = __DIR__ . '/../translations';
$console = __DIR__ . '/console';

foreach ($langs as $lang) {
    $poFile = "$translationsDir/$domain.$lang.po";

    // Run Symfony extractor in dump mode
    exec("php $console translation:extract --dump-messages $lang --domain=$domain 2>&1", $lines, $code);
    if ($code !== 0) {
        echo "ERROR: extractor failed for $lang\n";
        continue;
    }

    // Parse " * msgid" lines from the output
    $extracted = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (str_starts_with($line, '* ')) {
            $extracted[] = substr($line, 2);
        }
    }
    $extracted = array_unique($extracted);

    // Load existing .po content
    if (!file_exists($poFile)) {
        file_put_contents($poFile, implode("\n", [
            "# " . ucwords(str_replace('_', ' ', $lang)) . " translations for make-cargo-client.",
            'msgid ""',
            'msgstr ""',
            '"Content-Type: text/plain; charset=UTF-8\n"',
            '"Content-Transfer-Encoding: 8bit\n"',
            "\"Language: $lang\n\"",
            '',
        ]));
    }
    $content = file_get_contents($poFile);

    // Find already-present msgids
    preg_match_all('/^msgid "(.+)"$/m', $content, $m);
    $existing = array_flip($m[1]);

    // Append new entries (empty msgstr — translators fill these in)
    $added = 0;
    foreach ($extracted as $msg) {
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $msg);
        if (!isset($existing[$escaped])) {
            $content .= "\nmsgid \"$escaped\"\nmsgstr \"\"\n";
            $added++;
        }
    }

    file_put_contents($poFile, $content);
    echo "$lang: $added new entries added (" . count($extracted) . " total extracted)\n";
}

echo "Done. Edit translations/*.po to fill in msgstr values, then run:\n";
echo "  php bin/console cache:clear\n";
