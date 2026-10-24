<?php

declare(strict_types=1);

use LibreSign\WhatOSAmI\OperatingSystem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * SPDX-FileCopyrightText: 2024-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

final class OperatingSystemTest extends TestCase
{
    #[DataProvider('dataGetLinuxDistribution')]
    public function testGetLinuxDistribution(string $expected): void
    {
        $id = strtolower($expected);
        $tempFolder = sys_get_temp_dir();
        @mkdir($tempFolder . '/etc', 0755, true);
        file_put_contents(
            $tempFolder . '/etc/os-release',
            <<<OS_RELEASE
            NAME="$expected Linux"
            ID=$id
            VERSION_ID=3.20.1
            PRETTY_NAME="$expected Linux v3.20"
            HOME_URL="https://{$id}linux.org/"
            OS_RELEASE,
        );
        $instance = new OperatingSystem($tempFolder);
        $actual = $instance->getLinuxDistribution();
        $this->assertEquals(strtolower($expected), strtolower($actual));
    }

    #[DataProvider('dataSELinuxStatus')]
    public function testGetSELinuxStatus(
        string $content,
        string $expected
    ): void {
        $tempFolder = sys_get_temp_dir()
            . '/whatosami-selinux-' . bin2hex(random_bytes(8));

        $selinuxFolder = $tempFolder . '/sys/fs/selinux';
        $enforceFile = $selinuxFolder . '/enforce';

        try {
            $this->assertTrue(
                mkdir($selinuxFolder, 0755, true)
            );

            file_put_contents($enforceFile, $content);

            $instance = new OperatingSystem($tempFolder);

            $this->assertSame(
                $expected,
                $instance->getSELinuxStatus()
            );
        } finally {
            if (file_exists($enforceFile)) {
                unlink($enforceFile);
            }

            if (is_dir($selinuxFolder)) {
                rmdir($selinuxFolder);
                rmdir($tempFolder . '/sys/fs');
                rmdir($tempFolder . '/sys');
                rmdir($tempFolder);
            }
        }
    }

    #[RunInSeparateProcess]
    public function testGetSELinuxStatusWithOpenBasedirAllowed(): void
    {
        $tempFolder = sys_get_temp_dir()
            . '/whatosami-openbasedir-' . bin2hex(random_bytes(8));
        $selinuxFolder = $tempFolder . '/sys/fs/selinux';
        $enforceFile = $selinuxFolder . '/enforce';

        try {
            $this->assertTrue(mkdir($selinuxFolder, 0755, true));
            $this->assertNotFalse(file_put_contents($enforceFile, '1'));

            $result = $this->detectSELinuxWithOpenBasedir($tempFolder, $tempFolder);

            $this->assertSame([], $result['warnings']);
            $this->assertSame(OperatingSystem::SELINUX_ENFORCING, $result['status']);
        } finally {
            if (file_exists($enforceFile)) {
                unlink($enforceFile);
            }
            if (is_dir($selinuxFolder)) {
                rmdir($selinuxFolder);
                rmdir($tempFolder . '/sys/fs');
                rmdir($tempFolder . '/sys');
                rmdir($tempFolder);
            }
        }
    }

    #[RunInSeparateProcess]
    public function testGetSELinuxStatusWithOpenBasedirBlocked(): void
    {
        $tempFolder = sys_get_temp_dir()
            . '/whatosami-openbasedir-' . bin2hex(random_bytes(8));
        $selinuxFolder = $tempFolder . '/sys/fs/selinux';
        $enforceFile = $selinuxFolder . '/enforce';
        $allowedFolder = $tempFolder . '/allowed';

        try {
            $this->assertTrue(mkdir($selinuxFolder, 0755, true));
            $this->assertNotFalse(file_put_contents($enforceFile, '1'));
            $this->assertTrue(mkdir($allowedFolder));

            $result = $this->detectSELinuxWithOpenBasedir($tempFolder, $allowedFolder);

            $this->assertSame([], $result['warnings']);
            $this->assertSame(OperatingSystem::SELINUX_UNKNOWN, $result['status']);
        } finally {
            if (file_exists($enforceFile)) {
                unlink($enforceFile);
            }
            if (is_dir($allowedFolder)) {
                rmdir($allowedFolder);
            }
            if (is_dir($selinuxFolder)) {
                rmdir($selinuxFolder);
                rmdir($tempFolder . '/sys/fs');
                rmdir($tempFolder . '/sys');
                rmdir($tempFolder);
            }
        }
    }

    #[RunInSeparateProcess]
    public function testGetSELinuxStatusWithOpenBasedirDoubleLeadingSlash(): void
    {
        if (DIRECTORY_SEPARATOR !== '/') {
            $this->markTestSkipped('Double-leading-slash paths are tested on Unix systems.');
        }

        $tempFolder = sys_get_temp_dir()
            . '/whatosami-openbasedir-' . bin2hex(random_bytes(8));
        $selinuxFolder = $tempFolder . '/sys/fs/selinux';
        $enforceFile = $selinuxFolder . '/enforce';

        try {
            $this->assertTrue(mkdir($selinuxFolder, 0755, true));
            $this->assertNotFalse(file_put_contents($enforceFile, '1'));

            $regular = $this->detectSELinuxWithOpenBasedir($tempFolder, $tempFolder);
            $doubleSlash = $this->detectSELinuxWithOpenBasedir('/' . $tempFolder, $tempFolder);

            $this->assertSame([], $regular['warnings']);
            $this->assertSame(OperatingSystem::SELINUX_ENFORCING, $regular['status']);
            $this->assertSame([], $doubleSlash['warnings']);
            $this->assertSame(OperatingSystem::SELINUX_ENFORCING, $doubleSlash['status']);
        } finally {
            if (file_exists($enforceFile)) {
                unlink($enforceFile);
            }
            if (is_dir($selinuxFolder)) {
                rmdir($selinuxFolder);
                rmdir($tempFolder . '/sys/fs');
                rmdir($tempFolder . '/sys');
                rmdir($tempFolder);
            }
        }
    }

    #[RunInSeparateProcess]
    public function testGetSELinuxStatusWithOpenBasedirParentTraversal(): void
    {
        $tempFolder = sys_get_temp_dir()
            . '/whatosami-openbasedir-' . bin2hex(random_bytes(8));
        $selinuxFolder = $tempFolder . '/blocked/sys/fs/selinux';
        $enforceFile = $selinuxFolder . '/enforce';
        $allowedFolder = $tempFolder . '/allowed';

        try {
            $this->assertTrue(mkdir($selinuxFolder, 0755, true));
            $this->assertNotFalse(file_put_contents($enforceFile, '1'));
            $this->assertTrue(mkdir($allowedFolder));

            $result = $this->detectSELinuxWithOpenBasedir(
                $tempFolder . '/allowed/../blocked',
                $allowedFolder
            );

            $this->assertSame([], $result['warnings']);
            $this->assertSame(OperatingSystem::SELINUX_UNKNOWN, $result['status']);
        } finally {
            if (file_exists($enforceFile)) {
                unlink($enforceFile);
            }
            if (is_dir($allowedFolder)) {
                rmdir($allowedFolder);
            }
            if (is_dir($selinuxFolder)) {
                rmdir($selinuxFolder);
                rmdir($tempFolder . '/blocked/sys/fs');
                rmdir($tempFolder . '/blocked/sys');
                rmdir($tempFolder . '/blocked');
                rmdir($tempFolder);
            }
        }
    }

    #[RunInSeparateProcess]
    public function testGetSELinuxStatusWithOpenBasedirSimilarDirectoryPrefix(): void
    {
        $tempFolder = sys_get_temp_dir()
            . '/whatosami-openbasedir-' . bin2hex(random_bytes(8));
        $allowedFolder = $tempFolder . '/allowed';
        $blockedFolder = $tempFolder . '/allowed-extra';
        $selinuxFolder = $blockedFolder . '/sys/fs/selinux';
        $enforceFile = $selinuxFolder . '/enforce';

        try {
            $this->assertTrue(mkdir($allowedFolder, 0755, true));
            $this->assertTrue(mkdir($selinuxFolder, 0755, true));
            $this->assertNotFalse(file_put_contents($enforceFile, '1'));

            $result = $this->detectSELinuxWithOpenBasedir($blockedFolder, $allowedFolder);

            $this->assertSame([], $result['warnings']);
            $this->assertSame(OperatingSystem::SELINUX_UNKNOWN, $result['status']);
        } finally {
            if (file_exists($enforceFile)) {
                unlink($enforceFile);
            }
            if (is_dir($selinuxFolder)) {
                rmdir($selinuxFolder);
                rmdir($blockedFolder . '/sys/fs');
                rmdir($blockedFolder . '/sys');
                rmdir($blockedFolder);
            }
            if (is_dir($allowedFolder)) {
                rmdir($allowedFolder);
            }
            if (is_dir($tempFolder)) {
                rmdir($tempFolder);
            }
        }
    }

    #[RunInSeparateProcess]
    #[DataProvider('dataAllowedOpenBasedirPathVariants')]
    public function testGetSELinuxStatusWithOpenBasedirAllowedPathVariants(
        string $rootSuffix,
        bool $multipleAllowedDirectories,
        bool $unixOnly
    ): void {
        if ($unixOnly && DIRECTORY_SEPARATOR !== '/') {
            $this->markTestSkipped('Unix-specific path variation.');
        }

        $tempFolder = sys_get_temp_dir()
            . '/whatosami-openbasedir-' . bin2hex(random_bytes(8));
        $selinuxFolder = $tempFolder . '/target/sys/fs/selinux';
        $enforceFile = $selinuxFolder . '/enforce';
        $unrelatedFolder = $tempFolder . '/unrelated';

        try {
            $this->assertTrue(mkdir($selinuxFolder, 0755, true));
            $this->assertNotFalse(file_put_contents($enforceFile, '1'));
            $this->assertTrue(mkdir($unrelatedFolder));

            $allowedPaths = $multipleAllowedDirectories
                ? $unrelatedFolder . PATH_SEPARATOR . $tempFolder . '/target/'
                : $tempFolder;
            $result = $this->detectSELinuxWithOpenBasedir(
                $tempFolder . $rootSuffix,
                $allowedPaths
            );

            $this->assertSame([], $result['warnings']);
            $this->assertSame(OperatingSystem::SELINUX_ENFORCING, $result['status']);
        } finally {
            if (file_exists($enforceFile)) {
                unlink($enforceFile);
            }
            if (is_dir($selinuxFolder)) {
                rmdir($selinuxFolder);
                rmdir($tempFolder . '/target/sys/fs');
                rmdir($tempFolder . '/target/sys');
                rmdir($tempFolder . '/target');
            }
            if (is_dir($unrelatedFolder)) {
                rmdir($unrelatedFolder);
            }
            if (is_dir($tempFolder)) {
                rmdir($tempFolder);
            }
        }
    }

    public static function dataAllowedOpenBasedirPathVariants(): array
    {
        return [
            'dot and parent segments staying inside allowed root' => ['/target/../target/.', false, false],
            'multiple allowed directories with trailing separator' => ['/target', true, false],
            'repeated separators inside allowed root' => ['/target///', false, true],
        ];
    }

    #[RunInSeparateProcess]
    public function testGetSELinuxStatusWithOpenBasedirSymlinkEscapingAllowedDirectory(): void
    {
        if (DIRECTORY_SEPARATOR !== '/') {
            $this->markTestSkipped('Symbolic-link directory tests run on Unix systems.');
        }

        $tempFolder = sys_get_temp_dir()
            . '/whatosami-openbasedir-' . bin2hex(random_bytes(8));
        $allowedFolder = $tempFolder . '/allowed';
        $blockedFolder = $tempFolder . '/blocked';
        $link = $allowedFolder . '/linked';
        $enforceFile = $blockedFolder . '/sys/fs/selinux/enforce';
        $selinuxFolder = dirname($enforceFile);

        try {
            $this->assertTrue(mkdir($allowedFolder, 0755, true));
            $this->assertTrue(mkdir($selinuxFolder, 0755, true));
            $this->assertNotFalse(file_put_contents($enforceFile, '1'));
            $this->assertTrue(symlink($blockedFolder, $link));

            $result = $this->detectSELinuxWithOpenBasedir($link, $allowedFolder);

            $this->assertSame([], $result['warnings']);
            $this->assertSame(OperatingSystem::SELINUX_UNKNOWN, $result['status']);
        } finally {
            if (is_link($link)) {
                unlink($link);
            }
            if (file_exists($enforceFile)) {
                unlink($enforceFile);
            }
            if (is_dir($selinuxFolder)) {
                rmdir($selinuxFolder);
                rmdir($blockedFolder . '/sys/fs');
                rmdir($blockedFolder . '/sys');
                rmdir($blockedFolder);
            }
            if (is_dir($allowedFolder)) {
                rmdir($allowedFolder);
            }
            if (is_dir($tempFolder)) {
                rmdir($tempFolder);
            }
        }
    }

    #[RunInSeparateProcess]
    public function testGetSELinuxStatusWithOpenBasedirSymlinkResolvingInsideAllowedDirectory(): void
    {
        if (DIRECTORY_SEPARATOR !== '/') {
            $this->markTestSkipped('Symbolic-link directory tests run on Unix systems.');
        }

        $tempFolder = sys_get_temp_dir()
            . '/whatosami-openbasedir-' . bin2hex(random_bytes(8));
        $allowedFolder = $tempFolder . '/allowed';
        $outsideFolder = $tempFolder . '/outside';
        $link = $outsideFolder . '/linked';
        $enforceFile = $allowedFolder . '/sys/fs/selinux/enforce';
        $selinuxFolder = dirname($enforceFile);

        try {
            $this->assertTrue(mkdir($selinuxFolder, 0755, true));
            $this->assertTrue(mkdir($outsideFolder, 0755, true));
            $this->assertNotFalse(file_put_contents($enforceFile, '1'));
            $this->assertTrue(symlink($allowedFolder, $link));

            // PHP resolves the link to a file inside the allowed directory.
            $result = $this->detectSELinuxWithOpenBasedir($link, $allowedFolder);

            $this->assertSame([], $result['warnings']);
            $this->assertSame(OperatingSystem::SELINUX_ENFORCING, $result['status']);
        } finally {
            if (is_link($link)) {
                unlink($link);
            }
            if (file_exists($enforceFile)) {
                unlink($enforceFile);
            }
            if (is_dir($selinuxFolder)) {
                rmdir($selinuxFolder);
                rmdir($allowedFolder . '/sys/fs');
                rmdir($allowedFolder . '/sys');
                rmdir($allowedFolder);
            }
            if (is_dir($outsideFolder)) {
                rmdir($outsideFolder);
            }
            if (is_dir($tempFolder)) {
                rmdir($tempFolder);
            }
        }
    }

    #[RunInSeparateProcess]
    public function testGetSELinuxStatusWithOpenBasedirCurrentDirectory(): void
    {
        $tempFolder = sys_get_temp_dir()
            . '/whatosami-openbasedir-' . bin2hex(random_bytes(8));
        $enforceFile = $tempFolder . '/sys/fs/selinux/enforce';
        $selinuxFolder = dirname($enforceFile);

        try {
            $this->assertTrue(mkdir($selinuxFolder, 0755, true));
            $this->assertNotFalse(file_put_contents($enforceFile, '1'));

            // The nested PHP process changes cwd before enabling open_basedir='.'.
            $result = $this->detectSELinuxWithOpenBasedir($tempFolder, '.', $tempFolder);

            $this->assertSame([], $result['warnings']);
            $this->assertSame(OperatingSystem::SELINUX_ENFORCING, $result['status']);
        } finally {
            if (file_exists($enforceFile)) {
                unlink($enforceFile);
            }
            if (is_dir($selinuxFolder)) {
                rmdir($selinuxFolder);
                rmdir($tempFolder . '/sys/fs');
                rmdir($tempFolder . '/sys');
                rmdir($tempFolder);
            }
        }
    }

    #[RunInSeparateProcess]
    public function testGetSELinuxStatusWithOpenBasedirMissingFile(): void
    {
        $tempFolder = sys_get_temp_dir()
            . '/whatosami-openbasedir-' . bin2hex(random_bytes(8));

        try {
            $this->assertTrue(mkdir($tempFolder, 0755, true));

            $result = $this->detectSELinuxWithOpenBasedir($tempFolder, $tempFolder);

            $this->assertSame([], $result['warnings']);
            $this->assertSame(OperatingSystem::SELINUX_UNAVAILABLE, $result['status']);
        } finally {
            if (is_dir($tempFolder)) {
                rmdir($tempFolder);
            }
        }
    }

    /**
     * Run the restriction in a nested PHP process. PHPUnit writes isolation
     * results to the OS temp directory, so applying open_basedir inside the
     * PHPUnit worker itself would prevent PHPUnit from reporting the test.
     * The parent process can also reliably remove the temporary fixtures.
     *
     * @return array{status: string, warnings: list<string>}
     */
    private function detectSELinuxWithOpenBasedir(
        string $rootFolder,
        string $allowedFolder,
        ?string $workingDirectory = null
    ): array
    {
        $sourceFile = dirname(__DIR__, 2) . '/src/OperatingSystem.php';
        $script = <<<'PHP'
        require $argv[1];

        $warnings = [];
        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = $message;
            return true;
        });

        if (isset($argv[4]) && $argv[4] !== '' && !chdir($argv[4])) {
            fwrite(STDERR, 'Unable to change working directory');
            exit(2);
        }

        if (ini_set('open_basedir', $argv[3]) === false) {
            fwrite(STDERR, 'Unable to enable open_basedir');
            exit(2);
        }

        $status = (new \LibreSign\WhatOSAmI\OperatingSystem($argv[2]))->getSELinuxStatus();
        restore_error_handler();
        echo json_encode(['status' => $status, 'warnings' => $warnings], JSON_THROW_ON_ERROR);
        PHP;

        $command = [
            PHP_BINARY,
            '-r',
            $script,
            '--',
            $sourceFile,
            $rootFolder,
            $allowedFolder,
            $workingDirectory ?? '',
        ];
        $process = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );

        $this->assertIsResource($process, 'Unable to start isolated PHP process');
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $this->assertSame(0, $exitCode, $errors);
        $this->assertSame('', $errors);

        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }

    public static function dataSELinuxStatus(): array
    {
        return [
            'enforcing' => [
                '1',
                OperatingSystem::SELINUX_ENFORCING,
            ],
            'enforcing with newline' => [
                "1\n",
                OperatingSystem::SELINUX_ENFORCING,
            ],
            'permissive' => [
                '0',
                OperatingSystem::SELINUX_PERMISSIVE,
            ],
            'permissive with newline' => [
                "0\n",
                OperatingSystem::SELINUX_PERMISSIVE,
            ],
            'empty content' => [
                '',
                OperatingSystem::SELINUX_UNKNOWN,
            ],
            'invalid content' => [
                'unexpected',
                OperatingSystem::SELINUX_UNKNOWN,
            ],
        ];
    }

    public function testGetSELinuxStatusUnavailable(): void
    {
        $tempFolder = sys_get_temp_dir()
            . '/whatosami-selinux-' . bin2hex(random_bytes(8));

        mkdir($tempFolder, 0755, true);

        try {
            $instance = new OperatingSystem($tempFolder);

            $this->assertSame(
                OperatingSystem::SELINUX_UNAVAILABLE,
                $instance->getSELinuxStatus()
            );
        } finally {
            rmdir($tempFolder);
        }
    }

    public static function dataGetLinuxDistribution(): array
    {
        return [
            ['Alpine'],
            ['Debian'],
        ];
    }
}
