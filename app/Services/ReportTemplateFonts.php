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
        $windowsDirectory = getenv('WINDIR');
        $fontDirectory = $windowsDirectory
            ? realpath($windowsDirectory . DIRECTORY_SEPARATOR . 'Fonts')
            : false;

        if ($fontDirectory === false) {
            throw new RuntimeException('The report template fonts require the Windows system font directory.');
        }

        $fontFiles = [
            'oldEnglish' => $fontDirectory . DIRECTORY_SEPARATOR . 'OLDENGL.TTF',
            'timesNewRoman' => $fontDirectory . DIRECTORY_SEPARATOR . 'times.ttf',
        ];

        foreach ($fontFiles as $fontFile) {
            if (! is_readable($fontFile)) {
                throw new RuntimeException("A required report template font is missing: {$fontFile}");
            }
        }

        $fontCacheDirectory = storage_path('fonts');
        if (! is_dir($fontCacheDirectory) && ! mkdir($fontCacheDirectory, 0755, true) && ! is_dir($fontCacheDirectory)) {
            throw new RuntimeException("The report font cache directory could not be created: {$fontCacheDirectory}");
        }

        if (! is_writable($fontCacheDirectory)) {
            throw new RuntimeException("The report font cache directory is not writable: {$fontCacheDirectory}");
        }

        return [
            'oldEnglish' => 'file://' . str_replace('\\', '/', $fontFiles['oldEnglish']),
            'timesNewRoman' => 'file://' . str_replace('\\', '/', $fontFiles['timesNewRoman']),
            'chroot' => [realpath(base_path()), $fontDirectory],
        ];
    }
}
