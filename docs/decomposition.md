# Contributing to the term decomposition

Thanks for your interest in contributing to the decomposition process!  
This project is intentionally pragmatic: we focus on **fast, deterministic, lexeme-based decompounding** with a small 
emory footprint. Contributions that improve correctness, coverage, or maintainability are very welcome.

This document explains **where things live**, **how dictionaries are built**, and **how to contribute improvements or 
new languages**.

---

## Project structure overview

### `src/Tokenizer/Decompounder`

Contains the runtime logic:
- `Decompounder` – the core decomposition algorithm
- Dictionary access, configuration, and supporting classes

This code is performance-critical. Please keep changes here focused and well-justified.

---

### `build/`

Everything related to **building dictionaries** lives here.

- Dictionary builders
- Preprocessing logic
- Compression / normalization steps

This directory is intentionally separated from runtime code so that:

- dictionary building can be slow or complex
- runtime code stays small, fast, and memory-efficient

#### Locale-specific builders

Each supported language has its own builder, for example:
- `GermanBuilder`
- `EnglishBuilder`

These builders are responsible for:

- loading raw lexical data
- filtering terms
- normalizing surface forms
- producing a gzipped, ready-to-ship dictionary

> Currently, builders use data from **https://kaikki.org**, but this is **not a hard requirement**.  
> Any high-quality lexical source can be used as long as the output format matches what the `Decompounder` expects.

---

### `bin/console`

Dictionary building and profiling is done via Symfony console commands.

#### Build dictionaries

```bash
php bin/console build <?locale>
```

This command produces the compressed dictionary files ready to ship.

#### Profile memory usage

```bash
php bin/console profile <locale>
```

This command:

- loads the built dictionary into memory
- measures and reports memory usage
- helps evaluate whether a dictionary is suitable for production use

If you modify dictionary builders, **always run the profiler**.

---

## Functional tests

For each supported language, we have **functional decomposition tests** in:

```
tests/Tokenizer/Fixtures/Decomposition/
```

These tests define **expected splits** for real words and compounds.

### Test format

Each line is a comma-separated list:

```
originalterm,expected,split,parts
```

- The first value is the normalized input term
- The remaining values are the expected decomposition results
- If a term should **not** be decomposed, it appears alone on the line

Example:
```
eierbecher,ei,becher
datenbankserver,datenbank,daten,bank,server
```

### How to contribute fixes

If you find that:
- a term is decomposed incorrectly
- a valid decomposition is missing
- a new edge case appears

👉 **Add or update a test case first**, then adjust the code or dictionary builder until the test passes.

Functional tests are the primary way we validate correctness.

---

## Contributing a new language / locale

We are happy to accept new languages — but they must meet a minimum quality bar.

If you want to contribute a **new pre-built, ready-to-ship locale configuration**, please ensure:

1. A locale-specific builder exists in `build/`
2. The dictionary can be built via:
   ```bash
   php bin/console build <locale>
   ```
3. The dictionary loads successfully and passes profiling
4. **At least ~100 functional test cases** exist in:
   ```
   tests/Tokenizer/Fixtures/Decomposition/<locale>/
   ```

These tests don’t need to cover the full linguistic complexity of the language — that’s impossible — but they should demonstrate that:
- common compounds work
- obvious errors are avoided
- the produced splits make sense in practice

If fewer than ~100 tests exist, it’s very hard to judge whether a locale works reliably.

Thanks for contributing! 🚀
