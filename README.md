# SPSS / PSPP

A PHP library for reading and writing SPSS / PSPP .sav data files.
This library was forked from tiamo/spss since the original is not seeing a lot of activity.

VERSION 2.1.0 ([CHANGELOG](CHANGELOG.md))

[![Build Status](https://travis-ci.com/collecthor/spss.svg?branch=master)](https://travis-ci.com/collecthor/spss)
[![Latest Stable Version](https://poser.pugx.org/collecthor/spss/v/stable)](https://packagist.org/packages/collecthor/spss)
[![Total Downloads](https://poser.pugx.org/collecthor/spss/downloads)](https://packagist.org/packages/collecthor/spss)
[![Latest Unstable Version](https://poser.pugx.org/collecthor/spss/v/unstable)](https://packagist.org/packages/collecthor/spss)
[![License](https://poser.pugx.org/collecthor/spss/license)](https://packagist.org/packages/collecthor/spss)
[![Code Coverage](https://scrutinizer-ci.com/g/collecthor/spss/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/collecthor/spss/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/collecthor/spss/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/collecthor/spss/?branch=master)
# Plans

The plan is, in time, to fully rewrite this library to allow for streaming large datasets.

## Requirements

* PHP 7.3.0 and up (this fork will not support PHP versions that do not have [active support](https://www.php.net/supported-versions.php))
* mbstring extension
* bcmath extension

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
composer require collecthor/spss
```

or add

```
"tiamo/spss": "*"
```

to the require section of your `composer.json` [file](https://packagist.org/packages/tiamo/spss)
or download from [here](https://github.com/tiamo/spss/releases).

## Usage

Reader example:

```php
// Initialize reader
$reader = \collecthor\spss\Reader::fromFile('path/to/file.sav');

// Read header data
$reader->readHeader();
// var_dump($reader->header);

// Read full data
$reader->read();
// var_dump($reader->variables);
// var_dump($reader->valueLabels);
// var_dump($reader->documents);
// var_dump($reader->data);
```
or
```php
$reader = \collecthor\spss\Reader::fromString(file_get_contents('path/to/file.sav'))->read();
```

Writer example:

```php
$writer = new \collecthor\spss\Writer([
    'header' => [
            'prodName'     => '@(#) SPSS DATA FILE test',
            'layoutCode'   => 2,
            'compression'  => 1,
            'weightIndex'  => 0,
            'bias'         => 100,
            'creationDate' => '01 Feb 01',
            'creationTime' => '01:01:01',
    ],
    'variables' => [
        [
                'name'     => 'VAR1', # For UTF-8, 64 / 3 = 21, mb_substr($var1, 0, 21);
                'width'    => 0,
                'decimals' => 0,
                'format'   => 5,
                'columns'  => 50,
                'align'    => 1,
                'measure'  => 1,
                'data'     => [
                    1, 2, 3
                ],
        ],
        ...
    ]
]);
```

## Changelog

Please have a look in [CHANGELOG](CHANGELOG.md)

## License

Licensed under the [MIT license](http://opensource.org/licenses/MIT).
