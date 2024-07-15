<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace LibreSign\WhatOSAmI;

class OperatingSystem
{
    public function __construct(
        private string $rootFolder = '/',
    ) {
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
        foreach (glob($file) as $path) {
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
        if (file_exists($this->rootFolder . '/etc/debian_version')) {
            return 'Debian';
        }
        if (!$this->runSafe('type apt 2>/dev/null >/dev/null &')) {
            return '';
        }
        return 'Debian';
    }

    private function isAlpine(): string
    {
        if (!$this->runSafe('type apk 2>/dev/null >/dev/null &')) {
            return '';
        }
        return 'Alpine';
    }

    private function runSafe(string $command): string
    {
        $output = shell_exec($command . ' 2>/dev/null >/dev/null &');
        return (string) $output;
    }
}
