<?php

namespace App\Services\Document;

interface DocxToPdfConverter
{
    public function convert(string $docxPath): string;
}
