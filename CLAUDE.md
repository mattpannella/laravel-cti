# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Laravel package implementing the Class Table Inheritance (CTI) pattern for Eloquent models. Stores shared attributes in a parent table and subtype-specific attributes in separate tables, maintaining proper database normalization. Supports Laravel 8–12 and PHP 8.1+.

## Commands

```bash
# Run tests (SQLite in-memory, no external DB needed)
composer test

# Run a single test
vendor/bin/phpunit --filter testMethodName

# Run tests with coverage
composer test-coverage

# Static analysis (PHPStan level 6)
composer test:types
```

## Architecture

The package uses a three-table pattern: **lookup table** (type definitions), **parent table** (shared columns), and **subtype tables** (type-specific columns).

### Core Flow

1. **Parent model** uses the `HasSubtypes` trait and defines `$subtypeMap`, `$subtypeKey`, and lookup table config
2. **Subtype models** extend `SubtypeModel` and declare `$subtypeTable`, `$subtypeAttributes`, and `$ctiParentClass`
3. When loading: `HasSubtypes::newFromBuilder()` reads the discriminator column, resolves the type label from the lookup table, and instantiates the correct subtype class
4. When saving: `SubtypeModel::save()` wraps parent + subtype writes in a DB transaction
5. When querying: `SubtypeQueryBuilder` auto-joins the subtype table when subtype columns appear in where/order clauses
6. Collections: `SubtypedCollection` batch-loads subtype data (one query per subtype table) to prevent N+1

### Key Classes

- **`SubtypeModel`** (`src/SubtypeModel.php`) — Abstract base for subtype models. Handles dual-table save/delete, fires `subtypeSaving`/`subtypeSaved`/`subtypeDeleting`/`subtypeDeleted` events
- **`HasSubtypes`** trait (`src/Traits/HasSubtypes.php`) — Applied to parent model. Morphs loaded instances into correct subtype class, caches type label resolutions
- **`BootsSubtypeModel`** trait (`src/Traits/BootsSubtypeModel.php`) — Auto-assigns discriminator on create, registers `SubtypeDiscriminatorScope` global scope
- **`SubtypeQueryBuilder`** (`src/SubtypeQueryBuilder.php`) — Intercepts `where()`/`orderBy()` to auto-join subtype table when subtype columns are referenced
- **`SubtypedCollection`** (`src/Support/SubtypedCollection.php`) — Batch-loads subtype attributes, grouped by class
- **`HasSubtypeRelations`** trait (`src/Traits/HasSubtypeRelations.php`) — Provides `subtypeHasOne()`, `subtypeHasMany()`, `subtypeBelongsTo()`, `subtypeBelongsToMany()`

### Important Behaviors

- Subtype model's `$table` must be set to the **parent** table name (not the subtype table)
- `SubtypeModel::__call()` proxies undefined methods to the parent model class, inheriting parent relationships
- Parent model casts are merged into subtype instances during `newFromBuilder()`
- `validateSubtypeColumns()` checks that `$subtypeAttributes` don't overlap parent table columns (cached per class)
- Returning `false` from `subtypeSaving` or `subtypeDeleting` event listeners halts the entire operation

## Testing

Tests are in `tests/Unit/SubtypeModelTest.php`. Fixtures in `tests/Fixtures/` define an Assessment (parent) → Quiz/Survey (subtypes) hierarchy with related models. The test database schema is created fresh per test using SQLite in-memory.

## CI

GitHub Actions tests against a matrix of PHP 8.0–8.2 × Laravel 8–12 (with version-appropriate exclusions).
