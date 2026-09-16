<?php
// /public_html/app/Services/ProductImport/ProductImporterInterface.php

declare(strict_types=1);

namespace App\Services\ProductImport;

interface ProductImporterInterface
{
    public function supports(string $url): bool;

    public function import(string $url): ImportedProduct;
}
