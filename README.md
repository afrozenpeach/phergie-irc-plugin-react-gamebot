# frozen-solid/phergie-irc-plugin-react-onenightrevolution

[Phergie](http://github.com/phergie/phergie-irc-bot-react/) plugin for Play One Night Revolution on IRC.

[![Build Status](https://secure.travis-ci.org/frozen-solid/phergie-irc-plugin-react-onenightrevolution.png?branch=master)](http://travis-ci.org/frozen-solid/phergie-irc-plugin-react-onenightrevolution)

## Install

The recommended method of installation is [through composer](http://getcomposer.org).

`php composer.phar require frozen-solid/phergie-irc-plugin-react-onenightrevolution`

See Phergie documentation for more information on
[installing and enabling plugins](https://github.com/phergie/phergie-irc-bot-react/wiki/Usage#plugins).

## Provided Commands

| Command    | Parameters        | Description           |
|:----------:|-------------------|-----------------------|
| {commmand} | [param1] [param2] | {description}         |
## Configuration

```php
return [
    'plugins' => [
        // configuration
        new \Phergie\Irc\Plugin\React\One Night Revolution Bot\Plugin([



        ])
    ]
];
```

## Tests

To run the unit test suite:

```
curl -s https://getcomposer.org/installer | php
php composer.phar install
./vendor/bin/phpunit
```

## License

Released under the BSD License. See `LICENSE`.
