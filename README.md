[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]
[![Software License][ico-license]](LICENSE.md)

#TNTSearch

A fully featured full text search engine written in PHP

##Instalation

The easiest way to install TNTSearch is via [composer](http://getcomposer.org/). Create the following `composer.json` file and run the `php composer.phar install` command to install it.

```json
{
    "require": {
        "teamtnt/tntsearch": "dev-master"
    }
}
```

##Examples

### Creating an index

In order to be able to make full text search queries you have to create an index.

Usage:
```php

    use TeamTNT\TNTSearch;

    $tnt = new TNTSearch;

    $tnt->loadConfig([
        'type'    => 'mysql',
        'db'      => 'bbc',
        'host'    => 'localhost',
        'user'    => 'user',
        'pass'    => 'oass',
        'storage' => '/var/www/tntsearch/examples/'
    ]);

    $indexer = $tnt->createIndex('bbc.index');
    $indexer->query('SELECT id, article FROM articles;');
    $indexer->run();

```
Note: Your select statment MUST contain an ID field.

### Searching

Searching for a phrase or keyword is trivial


```php
    use TeamTNT\TNTSearch;

    $tnt = new TNTSearch;

    $tnt->loadConfig($config);
    $tnt->selectIndex("bbc.index");

    $res = $tnt->search("This is a test search", 12);

    print_r($res); //returns 12 document ids that best match your query

    //to display the results you need an aditional query
    //SELECT * FROM articles WHERE id IN $res;
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