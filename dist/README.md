# EOLib Distribution

This is the distribution package for the EOLib PHP library, containing only the library code without the code generator and other development files.

## About EOLib

EOLib is a core PHP library for writing applications related to Endless Online. It provides functionality to read and write various EO data structures, such as client packets, server packets, and game files (EMF, EIF, ENF, ESF, ECF). The library also includes utility classes for data manipulation, encoding, encryption, and packet sequencing.

For more information about EOLib and its features, please visit the main repository: [ExileStudios/eolib-php](https://github.com/ExileStudios/eolib-php)

## Installation

To install the EOLib library in your project, you can use Composer:

```console
$ composer require exilestudios/eolib-php-dist
```

## Features

Read and write the following EO data structures:

- Client packets
- Server packets
- Endless Map Files (EMF)
- Endless Item Files (EIF)
- Endless NPC Files (ENF)
- Endless Spell Files (ESF)
- Endless Class Files (ECF)

Utilities:

- Data reader
- Data writer
- Number encoding
- String encoding
- Data encryption
- Packet sequencer

## Example Usage
Here's an example of how to use the PacketFamily class from the library:
```php
    <?php

    require_once 'vendor/exilestudios/eolib-php-dist/vendor/autoload.php';

    use Eolib\Protocol\Generated\Net\PacketFamily;

    // Access packet family constants
    echo PacketFamily::CONNECTION; // Output: 1
    echo PacketFamily::ACCOUNT; // Output: 2
    echo PacketFamily::CHARACTER; // Output: 3
    // ...

    // Use packet family constants in your code
    $packetFamily = PacketFamily::LOGIN;
    if ($packetFamily === PacketFamily::LOGIN) {
        // Handle login packet
        // ...
    }
```

## Documentation

The library documentation is available online at: [https://exilestudios.github.io/eolib-php](https://exilestudios.github.io/eolib-php)

You can browse the documentation to learn about the available classes, methods, and their usage.

For the most up-to-date documentation, please refer to the main repository: [ExileStudios/eolib-php](https://github.com/ExileStudios/eolib-php)

## Contributing

If you would like to contribute to the development of EOLib, please visit the main repository: [ExileStudios/eolib-php](https://github.com/ExileStudios/eolib-php)

There you will find information on how to set up the development environment, contribute code, and report issues.

## License
This library is open-source software licensed under the MIT license.