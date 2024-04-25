# EOLib

A core PHP library for writing applications related to Endless Online.

## Installation

```console
$ composer require exilestudios/eolib-php
$ composer run-script dump-autoload -d vendor/exilestudios/eolib-php
$ composer run-script build -d vendor/exilestudios/eolib-php
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

    require_once 'vendor/exilestudios/eolib-php/vendor/autoload.php';

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

## Development

### Requirements

- PHP 7.4 or higher
- Composer

### Setup

1. Clone the repository:
```console
$ git clone https://github.com/ExileStudios/eolib-php.git
$ cd eolib-php
```

2. Install dependencies:
```console
$ composer install
```

3. Fetch the latest protocol files:
```console
$ composer fetch-protocol
```

4. Generate the protocol code:
```console
$ composer generate-protocol
```

5. Generate the documentation:
```console
$ composer generate-docs
```

### Usage

To fetch the latest protocol files, generate the protocol code, and generate the documentation:
```console
$ composer build
```
The generated protocol code will be available in the Eolib/Protocol/Generated directory. Under the namespace `Protocol\Generated`.
The generated documentation will be available in the docs directory.

## License
This library is open-source software licensed under the MIT license.