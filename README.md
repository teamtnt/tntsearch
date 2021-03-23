[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]
[![Software License][ico-license]](LICENSE.md)
[![Build Status](https://img.shields.io/travis/teamtnt/tntsearch/master.svg?style=flat-square)](https://travis-ci.org/teamtnt/tntsearch)
[![Slack Status](https://img.shields.io/badge/slack-chat-E01563.svg?style=flat-square)](https://tntsearch.slack.com)

![TNTSearch](https://i.imgur.com/aYKsNYv.png)

# TNTSearch

TNTSearch is a full-text search (FTS) engine written entirely in PHP. A simple configuration allows you to add an amazing search experience in just minutes. Features include:

* Fuzzy search
* Search as you type
* Geo-search
* Text classification
* Stemming
* Custom tokenizers
* Bm25 ranking algorithm
* Boolean search
* Result highlighting
* Dynamic index updates (no need to reindex each time)
* Easily deployable via Packagist.org

We also created some demo pages that show tolerant retrieval with n-grams in action.
The package has a bunch of helper functions like Jaro-Winkler and Cosine similarity for distance calculations. It supports stemming for English, Croatian, Arabic, Italian, Russian, Portuguese and Ukrainian. If the built-in stemmers aren't enough, the engine lets you easily plugin any compatible snowball stemmer. Some forks of the package even support Chinese. And please contribute other languages!

Unlike many other engines, the index can be easily updated without doing a reindex or using deltas. 

**View** [online demo](http://tntsearch.tntstudio.us/) &nbsp;|&nbsp; **Follow us** on
[Twitter](https://twitter.com/tntstudiohr),
or [Facebook](https://www.facebook.com/tntstudiohr) &nbsp;|&nbsp;
**Visit our sponsors**:

<p align="center">
  <a href="https://m.do.co/c/ddfc227b7d18" target="_blank">
    <img src="https://images.prismic.io/www-static/49aa0a09-06d2-4bba-ad20-4bcbe56ac507_logo.png?auto=compress,format" width="196.5" height="32">
  </a>
</p>

---
## Demo

* [TV Shows Search](http://tntsearch.tntstudio.us/)
* [PHPUnit Documentation Search](http://phpunit.tntstudio.us)
* [City Search with n-grams](http://cities.tnt.studio/)

## Tutorials

* [Solving the search problem with Laravel and TNTSearch](https://tnt.studio/solving-the-search-problem-with-laravel-and-tntsearch)
* [Searching for Users with Laravel Scout and TNTSearch](https://tnt.studio/searching-for-users-with-laravel-scout-and-tntsearch)

## Premium products

If you're using TNT Search and finding it useful, take a look at our premium analytics tool:


[<img src="https://i.imgur.com/ujagviB.png" width="420px" />](https://analytics.tnt.studio)

## Support us on Open Collective

- [TNTSearch](https://opencollective.com/tntsearch)

## Installation

The easiest way to install TNTSearch is via [composer](http://getcomposer.org/):

```
composer require teamtnt/tntsearch
```

## Requirements

Before you proceed, make sure your server meets the following requirements:

* PHP >= 7.1
* PDO PHP Extension
* SQLite PHP Extension
* mbstring PHP Extension

## Examples

### Creating an index

In order to be able to make full text search queries, you have to create an index.

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
    'storage'   => '/var/www/tntsearch/examples/',
    'stemmer'   => \TeamTNT\TNTSearch\Stemmer\PorterStemmer::class//optional
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

By default, the primary key isn't searchable. If you want to make it searchable, simply run:


```php
$indexer->includePrimaryKey();
```

### Searching

Searching for a phrase or keyword is trivial:

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

The ORDER BY FIELD clause is important, otherwise the database engine will not return
the results in the required order.

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
public $fuzzy_distance       = 2; //represents the Levenshtein distance;
```

```php
use TeamTNT\TNTSearch\TNTSearch;

$tnt = new TNTSearch;

$tnt->loadConfig($config);
$tnt->selectIndex("name.index");
$tnt->fuzziness = true;

//when the fuzziness flag is set to true, the keyword juleit will return
//documents that match the word juliet, the default Levenshtein distance is 2
$res = $tnt->search("juleit");

```
## Updating the index

Once you created an index, you don't need to reindex it each time you make some changes 
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

## Custom Tokenizer
First, create your own Tokenizer class. It should extend AbstractTokenizer class, define 
word split $pattern value and must implement TokenizerInterface:

``` php

use TeamTNT\TNTSearch\Support\AbstractTokenizer;
use TeamTNT\TNTSearch\Support\TokenizerInterface;

class SomeTokenizer extends AbstractTokenizer implements TokenizerInterface
{
    static protected $pattern = '/[\s,\.]+/';

    public function tokenize($text) {
        return preg_split($this->getPattern(), strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
    }
}
```

This tokenizer will split words using spaces, commas and periods.

After you have the tokenizer ready, you should pass it to `TNTIndexer` via `setTokenizer` method.

``` php
$someTokenizer = new SomeTokenizer;

$indexer = new TNTIndexer;
$indexer->setTokenizer($someTokenizer);
```

Another way would be to pass the tokenizer via config:

```php
use TeamTNT\TNTSearch\TNTSearch;

$tnt = new TNTSearch;

$tnt->loadConfig([
    'driver'    => 'mysql',
    'host'      => 'localhost',
    'database'  => 'dbname',
    'username'  => 'user',
    'password'  => 'pass',
    'storage'   => '/var/www/tntsearch/examples/',
    'stemmer'   => \TeamTNT\TNTSearch\Stemmer\PorterStemmer::class//optional,
    'tokenizer' => \TeamTNT\TNTSearch\Support\SomeTokenizer::class
]);

$indexer = $tnt->createIndex('name.index');
$indexer->query('SELECT id, article FROM articles;');
$indexer->run();

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

## PS4Ware

You're free to use this package, but if it makes it to your production environment, we would highly appreciate you sending us a PS4 game of your choice. This way you support us to further develop and add new features.

Our address is: TNT Studio, Sv. Mateja 19, 10010 Zagreb, Croatia.

We'll publish all received games [here][link-ps4ware]

[link-ps4ware]: https://github.com/teamtnt/tntsearch/blob/master/PS4Ware.md

## Support [![OpenCollective](https://opencollective.com/tntsearch/backers/badge.svg)](#backers) [![OpenCollective](https://opencollective.com/tntsearch/sponsors/badge.svg)](#sponsors)

<a href='https://ko-fi.com/O4O3K2R9' target='_blank'><img height='36' style='border:0px;height:36px;' src='https://az743702.vo.msecnd.net/cdn/kofi4.png?v=0' border='0' alt='Buy Me a Coffee at ko-fi.com' /></a>

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
