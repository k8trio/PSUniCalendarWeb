<?php

namespace App\Services;

use RuntimeException;

class ReportTemplateFonts
{
    /**
     * @return array{oldEnglish: string, timesNewRoman: string, chroot: array{string, string}}
     */
    public function forPdf(): array
    {
        $fontDirectory = realpath(public_path('fonts'));

        if ($fontDirectory === false) {
            throw new RuntimeException(
                'The report font directory was not found: ' . public_path('fonts')
            );
        }

        $fontFiles = [
            'oldEnglish' => $fontDirectory . DIRECTORY_SEPARATOR . 'OLDENGL.TTF',
            'timesNewRoman' => $fontDirectory . DIRECTORY_SEPARATOR . 'TIMES.TTF',
        ];

        foreach ($fontFiles as $fontFile) {
            if (! is_readable($fontFile)) {
                throw new RuntimeException("A required report template font is missing or unreadable: {$fontFile}");
            }
        }

        $fontCacheDirectory = storage_path('fonts');

        if (
            ! is_dir($fontCacheDirectory)
            && ! mkdir($fontCacheDirectory, 0755, true)
            && ! is_dir($fontCacheDirectory)
        ) {
            throw new RuntimeException(
                "The report font cache directory could not be created: {$fontCacheDirectory}"
            );
        }

        if (! is_writable($fontCacheDirectory)) {
            throw new RuntimeException(
                "The report font cache directory is not writable: {$fontCacheDirectory}"
            );
        }

        $oldEnglishFont = realpath($fontFiles['oldEnglish']);
        $timesNewRomanFont = realpath($fontFiles['timesNewRoman']);
        $baseDirectory = realpath(base_path());

        if ($oldEnglishFont === false || $timesNewRomanFont === false || $baseDirectory === false) {
            throw new RuntimeException('Could not resolve the report font or application paths.');
        }

        return [
            'oldEnglish' => 'file://' . str_replace('\\', '/', $oldEnglishFont),
            'timesNewRoman' => 'file://' . str_replace('\\', '/', $timesNewRomanFont),
            'chroot' => [$baseDirectory, $fontDirectory],
        ];
    }
}