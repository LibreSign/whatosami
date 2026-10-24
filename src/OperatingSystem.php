<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace LibreSign\WhatOSAmI;

class OperatingSystem
{
    public const SELINUX_ENFORCING = 'enforcing';
    public const SELINUX_PERMISSIVE = 'permissive';
    public const SELINUX_UNAVAILABLE = 'unavailable';
    public const SELINUX_UNKNOWN = 'unknown';

    public function __construct(
        private string $rootFolder = '/',
    ) {
    }

    /**
     * Returns the current SELinux enforcement status.
     */
    public function getSELinuxStatus(): string
    {
        $path = $this->rootFolder . '/sys/fs/selinux/enforce';

        // Let PHP resolve the effective path, including symbolic links.
        // A blocked open_basedir check emits a warning; keep it local and
        // report UNKNOWN rather than confusing restricted access with absence.
        $restrictedAccess = false;
        if (ini_get('open_basedir')) {
            set_error_handler(static function () use (&$restrictedAccess): bool {
                $restrictedAccess = true;
                return true;
            }, E_WARNING);

            try {
                $exists = file_exists($path);
            } finally {
                restore_error_handler();
            }
        } else {
            $exists = file_exists($path);
        }

        if ($restrictedAccess) {
            return self::SELINUX_UNKNOWN;
        }

        if (!$exists) {
            return self::SELINUX_UNAVAILABLE;
        }

        if (!is_readable($path)) {
            return self::SELINUX_UNKNOWN;
        }

        $value = @file_get_contents($path);

        if ($value === false) {
            return self::SELINUX_UNKNOWN;
        }

        return match (trim($value)) {
            '1' => self::SELINUX_ENFORCING,
            '0' => self::SELINUX_PERMISSIVE,
            default => self::SELINUX_UNKNOWN,
        };
    }

    /**
     * Returns PHP_OS_FAMILY (if defined (which it is on PHP >= 7.2)).
     * Returns a string (compatible with PHP_OS_FAMILY) derived from PHP_OS otherwise.
     *
     * References:
     * - https://github.com/sebastianbergmann/environment/blob/5.1.5/src/OperatingSystem.php
     * - https://github.com/sebastianbergmann/environment/issues/21
     * - https://unix.stackexchange.com/questions/6345/how-can-i-get-distribution-name-and-version-number-in-a-simple-shell-script
     */
    public function getFamily(): string
    {
        if (defined('PHP_OS_FAMILY')) {
            return PHP_OS_FAMILY;
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            return 'Windows';
        }

        switch (PHP_OS) {
            case 'Darwin':
                return 'Darwin';

            case 'DragonFly':
            case 'FreeBSD':
            case 'NetBSD':
            case 'OpenBSD':
                return 'BSD';

            case 'Linux':
                return 'Linux';

            case 'SunOS':
                return 'Solaris';

            default:
                return 'Unknown';
        }
    }

    public function getLinuxDistribution(): string
    {
        if (!$this->getFamily() === 'Linux') {
            return 'Unknown';
        }
        if ($distro = $this->getFromEtcRelease()) {
            return $distro;
        }
        if ($distro = $this->getFromLsbRelease()) {
            return $distro;
        }
        if ($distro = $this->isDebian()) {
            return $distro;
        }
        if ($distro = $this->isAlpine()) {
            return $distro;
        }
        return '';
    }

    private function getFromEtcRelease(): string
    {
        $file = $this->rootFolder . '/etc/*-release';
        $filesFound = glob($file);
        if (!$filesFound) {
            return '';
        }
        foreach ($filesFound as $path) {
            $content = file_get_contents($path);
            preg_match('/^ID=(?<version>.*)$/m', $content, $matches);
            if (isset($matches['version'])) {
                return $matches['version'];
            }
        }
        return '';
    }

    public function getFromLsbRelease(): string
    {
        if (!$output = $this->runSafe('lsb_release')) {
            return '';
        }
        $output = shell_exec('lsb_release --id');
        preg_match('/^Distributor ID: *(?<version>.*)$/m', $output, $matches);
        if (isset($matches['version'])) {
            return $matches['version'];
        }
        return '';
    }

    private function isDebian(): string
    {
        if ($this->isInOpenBasedir($this->rootFolder . '/etc/debian_version')) {
            if (file_exists($this->rootFolder . '/etc/debian_version')) {
                return 'Debian';
            }
        }
        if (!$this->runSafe('type apt')) {
            return '';
        }
        return 'Debian';
    }

    private function isAlpine(): string
    {
        if (!$this->runSafe('type apk')) {
            return '';
        }
        return 'Alpine';
    }

    private function runSafe(string $command): string
    {
        $output = shell_exec($command . ' 2>/dev/null');
        return (string) $output;
    }

    private function isInOpenBasedir(string $path): bool
    {
        $openBasedir = ini_get('open_basedir');
        if ($openBasedir === false || $openBasedir === '') {
            return true;
        }

        $normalizedPath = $this->normalizePathForOpenBasedir($path);
        if ($normalizedPath === null) {
            return false;
        }

        foreach (explode(PATH_SEPARATOR, $openBasedir) as $directory) {
            $normalizedDirectory = $this->normalizePathForOpenBasedir($directory);
            if ($normalizedDirectory === null) {
                continue;
            }

            if ($normalizedDirectory === '/' ||
                $normalizedPath === $normalizedDirectory ||
                str_starts_with($normalizedPath, rtrim($normalizedDirectory, '/') . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize separators, repeated slashes, and dot segments before checking
     * directory boundaries. No filesystem access is performed: PHP can reject
     * realpath/file_exists calls outside open_basedir with a warning.
     */
    private function normalizePathForOpenBasedir(string $path): ?string
    {
        $path = str_replace('\\', '/', $path);
        if ($path === '') {
            return null;
        }

        $drive = '';
        if (DIRECTORY_SEPARATOR === '\\' && preg_match('/^[a-z]:\//i', $path) === 1) {
            $drive = strtolower(substr($path, 0, 2));
            $path = substr($path, 2);
        } elseif (!str_starts_with($path, '/')) {
            $cwd = getcwd();
            if ($cwd === false) {
                return null;
            }
            $path = str_replace('\\', '/', $cwd) . '/' . $path;
            if (DIRECTORY_SEPARATOR === '\\' && preg_match('/^[a-z]:\//i', $path) === 1) {
                $drive = strtolower(substr($path, 0, 2));
                $path = substr($path, 2);
            }
        }

        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = DIRECTORY_SEPARATOR === '\\' ? strtolower($part) : $part;
        }

        return $drive . '/' . implode('/', $parts);
    }
}
