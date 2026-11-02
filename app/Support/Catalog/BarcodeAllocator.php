<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Models\Product;
use App\Support\Numbering\DocumentNumberer;

/** Auto barcodes: EAN-13 in the in-store range (20…), from a tenant sequence. Call inside Transaction::run(). */
final readonly class BarcodeAllocator
{
    public function __construct(private DocumentNumberer $numberer) {}

    public function next(): string
    {
        do {
            $code = Ean13::complete('20'.str_pad((string) (int) $this->numberer->next(DocumentNumberer::BARCODE), 10, '0', STR_PAD_LEFT));
        } while (Product::withTrashed()->where('barcode', $code)->exists());

        return $code;
    }
}
