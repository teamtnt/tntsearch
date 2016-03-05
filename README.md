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

    $res = $tnt->search("This is a test search", 10);

    print_r($res); //returns the rows containing the phrase
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.

## Credits

- [Nenad Tičarić][link-author]
- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.