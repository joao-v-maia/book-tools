<?php

namespace App\EventListener;

use League\Flysystem\FilesystemOperator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\DependencyInjection\Attribute\Target;

final class TemporaryStorageCleanupListener
{
    public function __construct(
        #[Target('temporary.storage')]
        private FilesystemOperator $storage
    ) {}
    
    #[AsEventListener]
    public function onTerminateEvent(TerminateEvent $event): void
    {
        foreach ($this->storage->listContents('/', true) as $item) {
            if ($item->isFile()) {
                $this->storage->delete($item->path());
            }
        }
    }
}
