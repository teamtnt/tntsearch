[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]
[![Software License][ico-license]](LICENSE.md)
[![Build Status](https://img.shields.io/travis/teamtnt/tntsearch/master.svg?style=flat-square)](https://travis-ci.org/teamtnt/tntsearch)
[![Slack Status](https://img.shields.io/badge/slack-chat-E01563.svg?style=flat-square)](https://tntsearch.slack.com)

# TNTSearch

A fully featured full text search engine written in PHP

![TNTSearch Banner](https://cloud.githubusercontent.com/assets/824840/17067635/edf2ae50-504c-11e6-9c63-a73955f55c29.jpg)

**View** [online demo](http://tntsearch.tntstudio.us/) &nbsp;|&nbsp; **Follow us** on
[Twitter](https://twitter.com/tntstudiohr),
or [Facebook](https://www.facebook.com/tntstudiohr) &nbsp;|&nbsp;
**Visit our sponsors**:

<p align="center">
  <a href="https://m.do.co/c/ddfc227b7d18" target="_blank">
    <img src="https://koistya.github.io/files/digital-ocean-393x64.png" width="196.5" height="32">
  </a>
</p>

---
## Demo

To see TNTSearch in action take a look at [the demo page](http://tntsearch.tntstudio.us/)

## Tutorials

* [Solving the search problem with Laravel and TNTSearch](http://tnt.studio/blog/solving-the-search-problem-with-laravel-and-tntsearch)
* [Searching for Bobby Fisher with Laravel 5](http://tnt.studio/blog/searching-for-bobby-fisher-with-laravel-5)
* [Did you mean functionality with Laravel Scout](http://tnt.studio/blog/did-you-mean-functionality-with-laravel-scout)

## Support us on Patreon

- [Nenad Ticaric](https://www.patreon.com/nticaric)
- [Sasa Tokic](https://www.patreon.com/stokic)

## Installation

The easiest way to install TNTSearch is via [composer](http://getcomposer.org/):

```
composer require teamtnt/tntsearch
```

## Requirements

Before you proceed make sure your server meets the following requirements:

* PHP >= 5.5
* PDO PHP Extension
* SQLite PHP Extension
* mbstring PHP Extension

## Examples

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

Note: If your primary key is different than `id` set it like:

```php
$indexer->setPrimaryKey('article_id');
```

### Making the primary key searchable

By default the primary key is not searchable, if you wanna make it searchable simply run:

```php
$indexer->includePrimaryKey();
```

### Searching

Searching for a phrase or keyword is trivial


```php
use TeamTNT\TNTSearch\TNTSearch;

$tnt = new TNTSearch;

$tnt->loadConfig($config);
$tnt->selectIndex("name.index");

$res = $tnt->search("This is a test search", 12);

print_r($res); //returns an array of 12 document ids that best match your query

// to display the results you need an additional query against your application database
// SELECT * FROM articles WHERE id IN $res ORDER BY FIELD(id, $res);
```

The ORDER BY FIELD clause is important otherwise the database engine will not return
the results in required order

### Boolean Search

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

### Fuzzy Search

The fuzziness can be tweaked by setting the following member variables:

```php
public $fuzzy_prefix_length  = 2;
public $fuzzy_max_expansions = 50;
public $fuzzy_distance       = 2 //represents the levenshtein distance;
```

```php
use TeamTNT\TNTSearch\TNTSearch;

$tnt = new TNTSearch;

$tnt->loadConfig($config);
$tnt->selectIndex("name.index");
$tnt->fuzziness = true;

//when the fuzziness flag is set to true the keyword juleit will return
//documents that match the word juliet, the default levenshtein distance is 2
$res = $tnt->search("juleit");

```
## Updating the index

Once you created an index you don't need to reindex it each time you make some changes 
to your document collection. TNTSearch supports dynamic index updates.

```php
use TeamTNT\TNTSearch\TNTSearch;

$tnt = new TNTSearch;

$tnt->loadConfig($config);
$tnt->selectIndex("name.index");

$index = $tnt->getIndex();

//to insert a new document to the index
$index->insert(['id' => '11', 'title' => 'new title', 'article' => 'new article']);

//to update an existing document
$index->update(11, ['id' => '11', 'title' => 'updated title', 'article' => 'updated article']);

//to delete the document from index
$index->delete(12);
```

## Geo Search

### Indexing

```php
$candyShopIndexer = new TNTGeoIndexer;
$candyShopIndexer->loadConfig($config);
$candyShopIndexer->createIndex('candyShops.index');
$candyShopIndexer->query('SELECT id, longitude, latitude FROM candy_shops;');
$candyShopIndexer->run();
```
### Searching

```php
$currentLocation = [
    'longitude' => 11.576124,
    'latitude'  => 48.137154
];

$distance = 2; //km

$candyShopIndex = new TNTGeoSearch();
$candyShopIndex->loadConfig($config);
$candyShopIndex->selectIndex('candyShops.index');

$candyShops = $candyShopIndex->findNearest($currentLocation, $distance, 10);
```

## Classification

```php
use TeamTNT\TNTSearch\Classifier\TNTClassifier;

$classifier = new TNTClassifier();
$classifier->learn("A great game", "Sports");
$classifier->learn("The election was over", "Not sports");
$classifier->learn("Very clean match", "Sports");
$classifier->learn("A clean but forgettable game", "Sports");

$guess = $classifier->predict("It was a close election");
var_dump($guess['label']); //returns "Not sports"

```

### Saving the classifier

```php
$classifier->save('sports.cls');
```

### Loading the classifier

```php
$classifier = new TNTClassifier();
$classifier->load('sports.cls');
```

## Drivers

* [TNTSearch Driver for Laravel Scout](https://github.com/teamtnt/laravel-scout-tntsearch-driver)

## Support [![OpenCollective](https://opencollective.com/tntsearch/backers/badge.svg)](#backers) [![OpenCollective](https://opencollective.com/tntsearch/sponsors/badge.svg)](#sponsors)

### Backers

Support us with a monthly donation and help us continue our activities. [[Become a backer](https://opencollective.com/tntsearch#backer)]

## Sponsors

Become a sponsor and get your logo on our README on Github with a link to your site. [[Become a sponsor](https://opencollective.com/tntsearch#sponsor)]

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

---
From Croatia with ♥ by TNT Studio ([@tntstudiohr](https://twitter.com/tntstudiohr), [blog](https://tnt.studio))
