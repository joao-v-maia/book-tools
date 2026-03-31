<?php

namespace App\Storage;

use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\File\File;

class TemporaryStorage
{
    public function __construct(
        #[Target('temporary.storage')]
        private readonly FilesystemOperator $disk,
        #[Autowire('%kernel.project_dir%/var/storage/temporary')]
        private readonly string $root,
    ) {
    }

    public function write(string $path, string $contents): void
    {
        $this->disk->write($path, $contents);
    }

    public function path(string $path): string
    {
        return $this->root.'/'.ltrim($path, '/');
    }

    public function file(string $path): File
    {
        return new File($this->path($path));
    }

    public function delete(string $path): void
    {
        $this->disk->delete($path);
    }
}
