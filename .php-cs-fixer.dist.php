<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

require_once './vendor-bin/coding-standard/vendor/autoload.php';

use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$config = new PhpCsFixer\Config();
$config
	->setParallelConfig(ParallelConfigFactory::detect())
	->getFinder()
	->ignoreVCSIgnored(true)
	->notPath('build')
	->notPath('vendor')
	->notPath('vendor-bin')
	->in(__DIR__);
return $config;
