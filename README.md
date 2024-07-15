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
$whatsOSAmI = new WhatsOSAmI();
$whatsOSAmI->getDistroName();
```

## How to contribute

Feel free to fork, improve and I will be very happy to receive pull requests with your improvements.

We will also be very happy if you support this project through LibreSign's GithHub Sponsor.
