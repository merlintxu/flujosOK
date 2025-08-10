<?php
namespace Tests;

use FlujosDimension\Core\Application;
use PHPUnit\Framework\TestCase;

class StorageDirectoriesTest extends TestCase
{
    public function testDirectoriesCreatedOnBootstrap(): void
    {
        $base = dirname(__DIR__) . '/storage';

        foreach (['recordings', 'voicemails'] as $sub) {
            $dir = $base . '/' . $sub;
            if (is_dir($dir)) {
                $files = glob($dir . '/*');
                if ($files !== false) {
                    array_map('unlink', $files);
                }
                @rmdir($dir);
            }
            $this->assertFalse(is_dir($dir));
        }

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
