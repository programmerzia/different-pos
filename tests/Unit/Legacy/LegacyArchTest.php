<?php

declare(strict_types=1);

it('never selects * or reads customer credentials in the legacy importer', function (): void {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 3).'/app/Legacy', FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        $source = (string) file_get_contents((string) $file->getPathname());
        expect($source)->not->toMatch('/select(Raw)?\(\s*[\'"]\*|SELECT \* FROM/i', "{$file->getFilename()} selects *");
    }

    $customers = (string) file_get_contents(dirname(__DIR__, 3).'/app/Legacy/Steps/CustomerGroupsAndCustomers.php');
    expect($customers)->not->toContain("'username'")->not->toContain("'passwd'");
});
