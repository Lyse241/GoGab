<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;

abstract class TestCase extends BaseTestCase
{
    /**
     * PDF de test au contenu réaliste (en-tête %PDF-) : la validation des documents
     * contrôle le vrai type du contenu, un fichier vide ne passerait pas.
     */
    protected function fakePdf(string $name = 'document.pdf', int $kilobytes = 10): UploadedFile
    {
        $content = "%PDF-1.4\n1 0 obj<< /Type /Catalog >>endobj\n";
        $content .= str_repeat('%', max(0, $kilobytes * 1024 - strlen($content) - 6))."\n%%EOF";

        return UploadedFile::fake()->createWithContent($name, $content);
    }
}
