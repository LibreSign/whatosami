<?php

declare(strict_types=1);

use LibreSign\WhatOSAmI\OperatingSystem;
use PHPUnit\Framework\Attributes\DataProvider;
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
