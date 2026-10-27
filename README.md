# What Operatinal System am I

What operational sytem am I

## Who am I

In some cases we need to know which operating system PHP is running on and also know this more specifically because if it is Linux, each Linux distribution has different characteristics and knowing which distribution it is can also be important for specific applications.

The original user case:
In the LibreSign project there is a dependency on Java applications, but Java has different compilations for different Linux distributions and different operating systems. In this context, this package came about.

## How to use

```bash
composer require -i libresign/whatsosami
```

Usage example:

```php
<?php
$operatingSystem = new OperatingSystem();
$operatingSystem->getLinuxDistribution();
```


### SELinux status detection

The `getSELinuxStatus()` method detects the current SELinux enforcement status without requiring administrative privileges.

Usage example:

```php
<?php

use LibreSign\WhatOSAmI\OperatingSystem;

$operatingSystem = new OperatingSystem();
$status = $operatingSystem->getSELinuxStatus();

if ($status === OperatingSystem::SELINUX_ENFORCING) {
    // SELinux is enforcing access-control rules.
}
```

Possible return values:

- `enforcing`: SELinux is active and enforcing security policies.
- `permissive`: SELinux is active but not enforcing security policies.
- `unavailable`: The SELinux status file does not exist.
- `unknown`: The SELinux status could not be determined.

The method reads `/sys/fs/selinux/enforce` and does not modify system settings.

## How to contribute

Feel free to fork, improve and I will be very happy to receive pull requests with your improvements.

We will also be very happy if you support this project through LibreSign's GithHub Sponsor.
