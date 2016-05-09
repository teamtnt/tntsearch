[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]
[![Software License][ico-license]](LICENSE.md)
[![Slack Status](https://img.shields.io/badge/slack-chat-E01563.svg?style=flat-square)](https://tntsearch.slack.com)

#TNTSearch

A fully featured full text search engine written in PHP

##Demo

To see TNTSearch in action take a look at [the demo page](http://tntsearch.tntstudio.us/)

##Installation

The easiest way to install TNTSearch is via [composer](http://getcomposer.org/). Create the following `composer.json` file and run the `php composer.phar install` command to install it.

```json
{
    "require": {
        "teamtnt/tntsearch": "0.4.*"
    }
}
```

##Examples

### Creating an index

In order to be able to make full text search queries you have to create an index.

Usage:
```php

    use TeamTNT\TNTSearch\TNTSearch;

    $tnt = new TNTSearch;

    $tnt->loadConfig([
        'driver'    => 'mysql',
        'host'      => 'localhost',
        'database'  => 'dbname',
        'username'  => 'user',
        'password'  => 'pass',
        'storage'   => '/var/www/tntsearch/examples/'
    ]);

    $indexer = $tnt->createIndex('name.index');
    $indexer->query('SELECT id, article FROM articles;');
    //$indexer->setLanguage('german');
    $indexer->run();

```

Important: "storage" settings marks the folder where all of your indexes
will be saved so make sure to have permission to write to this folder otherwise
you might expect the following exception thrown:

* [PDOException] SQLSTATE[HY000] [14] unable to open database file *

Note: Your select statment MUST contain an ID field.

### Searching

Searching for a phrase or keyword is trivial


```php
    use TeamTNT\TNTSearch\TNTSearch;

    $tnt = new TNTSearch;

    $tnt->loadConfig($config);
    $tnt->selectIndex("name.index");

    $res = $tnt->search("This is a test search", 12);

    print_r($res); //returns an array of 12 document ids that best match your query

    //to display the results you need an additional query
    //SELECT * FROM articles WHERE id IN $res ORDER BY FIELD(id, $res);
```

The ORDER BY FIELD clause is important otherwise the database engine will not return
the results in required order

### Boolean search

```php
    use TeamTNT\TNTSearch\TNTSearch;

    $tnt = new TNTSearch;

    $tnt->loadConfig($config);
    $tnt->selectIndex("name.index");

    //this will return all documents that have romeo in it but not juliet
    $res = $tnt->searchBoolean("romeo -juliet");
    
    //returns all documents that have romeo or hamlet in it
    $res = $tnt->searchBoolean("romeo or hamlet");
    
    //returns all documents that have either romeo AND juliet or prince AND hamlet
    $res = $tnt->searchBoolean("(romeo juliet) or (prince hamlet)");

```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.

## Credits

- [Nenad Tičarić][link-author]
- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/teamtnt/tntsearch.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/teamtnt/tntsearch.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/teamtnt/tntsearch
[link-downloads]: https://packagist.org/packages/teamtnt/tntsearch
[link-author]: https://github.com/nticaric
[link-contributors]: ../../contributors
