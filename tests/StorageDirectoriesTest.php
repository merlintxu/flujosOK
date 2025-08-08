<?php
namespace Tests;

use FlujosDimension\Core\Application;
use PHPUnit\Framework\TestCase;

class StorageDirectoriesTest extends TestCase
{
    public function testDirectoriesCreatedOnBootstrap(): void
    {
        $base = dirname(__DIR__) . '/storage';
        @rmdir($base . '/recordings');
        @rmdir($base . '/voicemails');

        $this->assertFalse(is_dir($base . '/recordings'));
        $this->assertFalse(is_dir($base . '/voicemails'));

        new Application();
        // Reset handlers installed during bootstrap to avoid risky test notices
        restore_error_handler();
        restore_exception_handler();

        $this->assertDirectoryExists($base . '/recordings');
        $this->assertDirectoryIsWritable($base . '/recordings');
        $this->assertDirectoryExists($base . '/voicemails');
        $this->assertDirectoryIsWritable($base . '/voicemails');
    }
}
