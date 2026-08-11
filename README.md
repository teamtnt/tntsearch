[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]
[![Software License][ico-license]](LICENSE.md)

![TNTSearch](https://i.imgur.com/aYKsNYv.png)

# TNTSearch

TNTSearch is a full-text search (FTS) engine written entirely in PHP. A simple configuration lets you add an amazing search experience in minutes. It stores its index in SQLite (default), MySQL, or Redis, and the index can be updated on the fly — no full reindex required.

Features: fuzzy search · search-as-you-type · boolean search · geo-search · BM25/TF-IDF ranking · stemming (many languages) · custom tokenizers · result highlighting · text classification · dynamic index updates.

**View** [online demo](http://tntsearch.tntstudio.us/) &nbsp;|&nbsp; **Follow us** on
[Twitter](https://twitter.com/tntstudiohr) or [Facebook](https://www.facebook.com/tntstudiohr) &nbsp;|&nbsp;
**Visit our sponsors**:

<p align="center">
  <a href="https://m.do.co/c/ddfc227b7d18" target="_blank">
    <img src="https://images.prismic.io/www-static/49aa0a09-06d2-4bba-ad20-4bcbe56ac507_logo.png?auto=compress,format" width="196.5" height="32">
  </a>
</p>

---

## 🤖 TL;DR for AI agents

**What it is:** a PHP library that builds an inverted index from your data and answers text queries against it. **It returns matching document IDs (and scores), not the documents themselves** — you fetch the rows from your own database using those IDs.

**Mental model — three steps:**

1. **Index** your data once → creates an index file (e.g. `articles.index`) in the `storage` folder.
2. **Select** that index.
3. **Search** it → get back an array of document IDs ordered by relevance.

**Minimal working example (SQLite, the default engine):**

```php
use TeamTNT\TNTSearch\TNTSearch;

$tnt = new TNTSearch;
$tnt->loadConfig([
    'driver'   => 'sqlite',              // where your SOURCE data lives
    'database' => __DIR__ . '/app.sqlite',
    'storage'  => __DIR__ . '/storage/', // where the INDEX is written (must be writable)
    'stemmer'  => \TeamTNT\TNTSearch\Stemmer\PorterStemmer::class, // optional
]);

// 1. Build the index from a query against your source data (run once).
$indexer = $tnt->createIndex('articles.index');
$indexer->query('SELECT id, title, article FROM articles;'); // first column = primary key
$indexer->run();

// 2. Select the index.
$tnt->selectIndex('articles.index');

// 3. Search it.
$res = $tnt->search('romeo and juliet', 20);
// $res = ['ids' => [7, 3, 10, ...], 'hits' => 42, 'docScores' => [...], 'execution_time' => '3.1 ms']

// 4. Fetch the actual rows yourself, preserving the order:
//    SELECT * FROM articles WHERE id IN (7,3,10) ORDER BY FIELD(id, 7,3,10)
```

**The rules that trip agents up (read these):**

- `search()` and `searchBoolean()` return **document IDs**, not rows. Fetch rows from your DB and keep the order (`ORDER BY FIELD(id, ...)` in MySQL).
- You must `createIndex()` **before** indexing and `selectIndex()` **before** searching. Searching a not-yet-selected index throws.
- The **first column** of the indexer `query()` is the primary key (default name `id`; change with `setPrimaryKey()`).
- `search()` uses **relevance/OR** semantics (a doc matching one term can still rank). Use `searchBoolean()` for **AND / OR / NOT** logic.
- The index is a file in `storage`; that folder must be writable (it is auto-created since v5.2). The source DB and the index are **separate** things.
- Stemmer and tokenizer are baked into the index **at index time** — set them when you create the index, not when you search.

Full API cheat-sheet is at the [bottom](#api-cheat-sheet).

---

## Installation

```bash
composer require teamtnt/tntsearch
```

**Requirements:** PHP >= 7.4 · PDO · `pdo_sqlite` (for the default engine) · `mbstring`.

> `PDOException: could not find driver` means the `pdo_sqlite` extension isn't enabled for the PHP SAPI running your code (CLI and web often differ) — it's an environment issue, not a library one.

## Configuration

`loadConfig(array $config)` accepts:

| Key | Required | Description |
|-----|----------|-------------|
| `driver` | yes | Source DB driver: `sqlite`, `mysql`, `pgsql`, `sqlsrv`, or `filesystem`. |
| `database` | for sqlite/db | Source database name / path (the data you index). |
| `host`, `username`, `password` | for mysql/pgsql | Source DB connection. |
| `storage` | yes | Folder where index files are written. Must be writable. |
| `engine` | no | Index backend: `SqliteEngine::class` (**default**), `MysqlEngine::class`, or `RedisEngine::class`. |
| `stemmer` | no | Stemmer class, e.g. `PorterStemmer::class`. Defaults to `NoStemmer`. |
| `tokenizer` | no | Tokenizer class. Defaults to `Tokenizer` (Unicode words, keeps digits/`_`/`-`/`@`). |
| `wal` | no | SQLite Write-Ahead Logging. Defaults to `true`. |
| `redis_host`, `redis_port` | for redis | Connection for `RedisEngine`. |
| `options` | no | Extra PDO options passed to the connection, e.g. `[PDO::MYSQL_ATTR_SSL_CA => '/path/ca.pem']` for managed MySQL (PlanetScale, TiDB Cloud) that require TLS. |

Filesystem indexing also uses `location` (directory to scan) and `extension` (e.g. `txt`).

```php
// Connecting to a managed MySQL provider that requires TLS:
$tnt->loadConfig([
    'driver'   => 'mysql',
    'host'     => 'aws.connect.psdb.cloud',
    'database' => 'mydb',
    'username' => 'user',
    'password' => 'pass',
    'storage'  => __DIR__ . '/storage/',
    'options'  => [
        PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/cert.pem',
    ],
]);
```

> **Engine choice:** `SqliteEngine` (default) is best for most cases and needs no server. `MysqlEngine` keeps the index in your MySQL DB. `RedisEngine` keeps it in Redis. All expose the same API.

## Indexing

### From a database (bulk)

```php
$indexer = $tnt->createIndex('articles.index');
$indexer->query('SELECT id, title, article FROM articles;'); // 1st column = primary key
// $indexer->setPrimaryKey('article_id'); // if the PK column isn't named "id"
// $indexer->includePrimaryKey();          // make the PK itself searchable (off by default)
// $indexer->setLanguage('german');        // pick a stemmer by language
$indexer->run();
```

`run()` streams the query and writes the index in batched transactions — use it for large datasets.

### Dynamic updates (no reindex needed)

```php
$tnt->selectIndex('articles.index');
$index = $tnt->getIndex();

$index->insert(['id' => 11, 'title' => 'new title', 'article' => 'new body']);
$index->update(11, ['id' => 11, 'title' => 'updated', 'article' => 'updated body']);
$index->delete(11);
```

Each `insert()` is wrapped in a single transaction (fast even for large documents).

### From the filesystem

```php
$tnt->loadConfig([
    'driver'    => 'filesystem',
    'location'  => __DIR__ . '/docs/',
    'extension' => 'txt',
    'storage'   => __DIR__ . '/storage/',
]);
$indexer = $tnt->createIndex('docs.index');
$indexer->run();
```

## Searching

### Relevance search

```php
$tnt->selectIndex('articles.index');
$res = $tnt->search('romeo and juliet', 20); // 2nd arg = max results (default 100)
```

Returns:

```php
[
  'ids'            => [7, 3, 10],          // document IDs, best match first
  'hits'           => 42,                  // total matching documents
  'docScores'      => [7 => 5.1, 3 => 4.8],// id => BM25 score
  'execution_time' => '3.1 ms',
]
```

`search()` sums per-term BM25 contributions, so it is OR-like: a document matching only one term can still appear.

### Boolean search

```php
$tnt->searchBoolean('romeo -juliet');            // has "romeo" but NOT "juliet"
$tnt->searchBoolean('romeo or hamlet');          // has "romeo" OR "hamlet"
$tnt->searchBoolean('romeo juliet');             // has "romeo" AND "juliet" (space = AND)
$tnt->searchBoolean('(romeo juliet) or (prince hamlet)');
```

Operators: space = AND, `or` / `|` = OR, `-term` or `~term` = NOT. Same return shape as `search()` (`docScores` is empty).

### Fuzzy search

```php
$tnt->fuzziness(true);
$res = $tnt->search('juleit'); // matches "juliet"
```

Tuning (defaults shown):

| Setter | Default | Meaning |
|--------|---------|---------|
| `setFuzzyPrefixLength($n)` | `2` | Candidates must share the first N characters. Lower it to match typos near the start (e.g. `1`). |
| `setFuzzyDistance($n)` | `2` | Max Levenshtein distance (measured in characters, multibyte-safe). |
| `setFuzzyMaxExpansions($n)` | `50` | Max candidate terms considered. |
| `setFuzzyNoLimit($bool)` | `false` | If `true`, also expand fuzzily even when an exact match exists. |

> Common gotcha: with `fuzzy_prefix_length = 2`, `yon` will **not** match `yann` (2nd character differs). Lower the prefix length for such cases.

### Search-as-you-type (prefix matching)

```php
$tnt->asYouType(true);
$res = $tnt->search('jul'); // matches "juliet", "julius", ...
```

## Highlighting & snippets

```php
$title = 'The tragedy of Romeo and Juliet';
echo $tnt->highlight($title, 'romeo juliet', 'em', ['wholeWord' => false]);
// The tragedy of <em>Romeo</em> and <em>Juliet</em>

$snippet = $tnt->snippet('romeo juliet', $fullArticleText); // relevant excerpt around the terms
```

`highlight($text, $needle, $tag = 'em', $options = [])` options: `wholeWord`, `caseSensitive`, `simple`, `stripLinks`, `tagOptions`.

## Stemming & languages

Set a stemmer via config (`'stemmer' => GermanStemmer::class`) or `$indexer->setLanguage('german')`. Built-in languages: `arabic`, `croatian`, `french`, `german`, `italian`, `latvian`, `polish`, `porter` (English), `portuguese`, `russian`, `ukrainian`, and `no` (no stemming). Any compatible Snowball stemmer can be plugged in.

## Custom tokenizers

Implement `TokenizerInterface` (or extend the default `Tokenizer`) and pass it via config or `setTokenizer()`:

```php
use TeamTNT\TNTSearch\Tokenizer\Tokenizer;
use TeamTNT\TNTSearch\Tokenizer\TokenizerInterface;

class CommaTokenizer extends Tokenizer implements TokenizerInterface
{
    static protected $pattern = '/[\s,\.]+/';

    public function tokenize($text, $stopwords = [])
    {
        return preg_split($this->getPattern(), mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
    }
}

$indexer->setTokenizer(new CommaTokenizer);
// or: 'tokenizer' => CommaTokenizer::class in loadConfig()
```

Included tokenizers: `Tokenizer` (default), `BigramTokenizer`, `TrigramTokenizer`, `FourgramTokenizer`, `FivegramTokenizer`, `NGramTokenizer`, `EdgeNgramTokenizer`, `ProductTokenizer`.

## Geo search

```php
// Index (columns: id, longitude, latitude)
$geo = new TeamTNT\TNTSearch\Indexer\TNTGeoIndexer;
$geo->loadConfig($config);
$geo->createIndex('shops.index');
$geo->query('SELECT id, longitude, latitude FROM shops;');
$geo->run();

// Search
$search = new TeamTNT\TNTSearch\TNTGeoSearch;
$search->loadConfig($config);
$search->selectIndex('shops.index');
$nearest = $search->findNearest(
    ['longitude' => 11.576124, 'latitude' => 48.137154],
    2,   // radius in km
    10   // max results
);
```

## Text classification

```php
use TeamTNT\TNTSearch\Classifier\TNTClassifier;

$classifier = new TNTClassifier();
$classifier->learn('A great game', 'Sports');
$classifier->learn('The election was over', 'Not sports');

$guess = $classifier->predict('It was a close election');
echo $guess['label']; // "Not sports"

$classifier->save('sports.cls');
$classifier->load('sports.cls');
```

## Gotchas & constraints

- **IDs, not rows.** Both search methods return IDs; join back to your data and preserve order (`ORDER BY FIELD(id, ...)`).
- **Order of operations.** `createIndex()` → `run()`/`insert()` to build; `selectIndex()` → `search()` to query.
- **`search()` ≠ `searchBoolean()`.** `search()` is relevance/OR; `searchBoolean()` is strict boolean.
- **Primary key.** First column of the index query is the PK. Not searchable unless you call `includePrimaryKey()`.
- **Index config is baked in.** The stemmer/tokenizer used at index time is stored in the index; searching uses the stored one.
- **SQLite is single-writer.** For high-concurrency indexing, use `run()` (batched) or the MySQL/Redis engine.
- **`storage` must be writable** (auto-created since v5.2).

## API cheat-sheet

**`TNTSearch`** — configuration & querying:

| Method | Purpose |
|--------|---------|
| `loadConfig(array $config)` | Configure and pick the engine. |
| `createIndex(string $name): TNTIndexer` | Start a new index. |
| `selectIndex(string $name)` | Choose the index to search. |
| `search(string $phrase, int $limit = 100): array` | Relevance search → `ids`, `hits`, `docScores`. |
| `searchBoolean(string $phrase, int $limit = 100): array` | Boolean (AND/OR/NOT) search. |
| `getIndex(): TNTIndexer` | Get the indexer for the selected index (for `insert`/`update`/`delete`). |
| `fuzziness(bool)`, `setFuzzy*`, `asYouType(bool)` | Toggle/tune fuzzy and prefix search. |
| `highlight(...)`, `snippet(...)` | Highlight terms / extract an excerpt. |
| `totalDocumentsInCollection(): int` | Number of indexed documents. |

**`TNTIndexer`** (from `createIndex()` / `getIndex()`) — building & updating:

| Method | Purpose |
|--------|---------|
| `query(string $sql)` | Source query; first column is the primary key. |
| `run()` | Bulk-build the index from the query. |
| `insert(array $doc)` / `update(int $id, array $doc)` / `delete(int $id)` | Dynamic updates. |
| `setPrimaryKey(string)` / `includePrimaryKey()` | Primary-key handling. |
| `setLanguage(string)` / `setStemmer(...)` / `setTokenizer(...)` | Index-time text processing. |
| `setStopWords(array)` | Words to ignore. |
| `disableOutput(bool)` | Silence progress output. |

## Drivers & integrations

* [TNTSearch Driver for Laravel Scout](https://github.com/teamtnt/laravel-scout-tntsearch-driver)

## Tutorials

* [Solving the search problem with Laravel and TNTSearch](https://tnt.studio/solving-the-search-problem-with-laravel-and-tntsearch)
* [Searching for Users with Laravel Scout and TNTSearch](https://tnt.studio/searching-for-users-with-laravel-scout-and-tntsearch)

## Demos

* [TV Shows Search](http://tntsearch.tntstudio.us/) · [PHPUnit Docs Search](http://phpunit.tntstudio.us) · [City Search with n-grams](http://cities.tnt.studio/)

## Premium products

If you find TNTSearch useful, take a look at our premium analytics tool:

[<img src="https://i.imgur.com/ujagviB.png" width="420px" />](https://analytics.tnt.studio)

## PS4Ware / PS5Ware

You're free to use this package, but if it makes it to your production environment, we'd highly appreciate you sending us a PS4/PS5 game of your choice. This way you support us to further develop and add new features.

Our address is: TNT Studio, Sv. Mateja 19, 10010 Zagreb, Croatia. We'll publish all received games [here][link-ps4ware].

[link-ps4ware]: https://github.com/teamtnt/tntsearch/blob/master/PS4Ware.md

## Support us on Open Collective

- [TNTSearch](https://opencollective.com/tntsearch)

## Support [![OpenCollective](https://opencollective.com/tntsearch/backers/badge.svg)](#backers) [![OpenCollective](https://opencollective.com/tntsearch/sponsors/badge.svg)](#sponsors)

<a href='https://ko-fi.com/O4O3K2R9' target='_blank'><img height='36' style='border:0px;height:36px;' src='https://az743702.vo.msecnd.net/cdn/kofi4.png?v=0' border='0' alt='Buy Me a Coffee at ko-fi.com' /></a>

### Backers

Support us with a monthly donation and help us continue our activities. [[Become a backer](https://opencollective.com/tntsearch#backer)]

### Sponsors

Become a sponsor and get your logo on our README on Github with a link to your site. [[Become a sponsor](https://opencollective.com/tntsearch#sponsor)]

## Credits

- [Nenad Tičarić][link-author]
- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see the [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/teamtnt/tntsearch.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/teamtnt/tntsearch.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/teamtnt/tntsearch
[link-downloads]: https://packagist.org/packages/teamtnt/tntsearch
[link-author]: https://github.com/nticaric
[link-contributors]: ../../contributors

---
From Croatia with ♥ by TNT Studio ([@tntstudiohr](https://twitter.com/tntstudiohr), [blog](https://tnt.studio))
