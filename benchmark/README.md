# Index creation benchmark

A small, self-contained harness for measuring SQLite index-creation speed.

## Usage

```bash
# 1. Generate a deterministic dataset (default: 20,000 documents)
php benchmark/generate.php 20000

# 2. Time index creation over it
php benchmark/bench.php
```

`bench.php` prints documents indexed, elapsed time, throughput (docs/s) and
peak memory.

## Why this dataset

`generate.php` builds a SQLite table of documents whose text is drawn from a
Zipfian vocabulary: ~80% of tokens come from a small set of 200 common words,
the rest from a long tail of 5,000 rare words. This skew mirrors real corpora
and, crucially, means almost every term already exists in the wordlist after
the first few documents — which is the path index creation spends most of its
time in. The generator is seeded (`mt_srand`) so runs are reproducible.

Generated files (`dataset.sqlite`, `_index/`) are gitignored.
