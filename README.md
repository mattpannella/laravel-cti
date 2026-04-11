# Laravel CTI

[![Tests](https://github.com/mattpannella/laravel-cti/actions/workflows/tests.yml/badge.svg)](https://github.com/mattpannella/laravel-cti/actions/workflows/tests.yml)
[![Latest Stable Version](https://poser.pugx.org/pannella/laravel-cti/v/stable)](https://packagist.org/packages/pannella/laravel-cti)
[![License](https://poser.pugx.org/pannella/laravel-cti/license)](https://packagist.org/packages/pannella/laravel-cti)

A Laravel package for implementing the Class Table Inheritance (CTI) pattern with Eloquent models. Shared attributes live in a parent table, type-specific attributes live in separate subtype tables, and the package handles type resolution, querying, and persistence automatically.

## Why CTI?

If you have a type hierarchy in Laravel (for example, `Quiz` and `Survey` are both types of `Assessment`), there are a few common ways to model it. Each has tradeoffs.

### Single Table Inheritance (STI)

One table holds every column for every type, with a discriminator column to distinguish them.

- **Pros:** Simple queries, no joins, easy to set up.
- **Cons:** You end up with a lot of nullable columns that don't apply to most rows, and the table gets wider every time you add a new type. This violates normalization: you're storing NULLs for columns that are structurally irrelevant to a given row, not just empty.

### Separate Tables

Each type gets its own table (`quizzes`, `surveys`) with shared columns duplicated in each one.

- **Pros:** Clean per-type schemas, no NULLs.
- **Cons:** Shared columns are duplicated across tables. There's no unified way to query "all assessments." If you need to change a shared attribute, you have to update every table.

### Polymorphic Relations

Laravel's `morphTo`/`morphMany` pattern stores a `*_type` and `*_id` pair so one entity can relate to multiple unrelated model types.

- **Pros:** Flexible, built into Eloquent, and works well for its intended purpose (e.g., a `Comment` that can belong to either a `Post` or a `Video`).
- **Cons:** Polymorphic relations are a relationship pattern, not an inheritance pattern. They solve "entity A relates to multiple unrelated entity types," not "entities A1 and A2 are specialized versions of entity A." Because the `*_id` column references different tables depending on the type value, you can't put a real foreign key constraint on it. Referential integrity is only enforceable in application code. Trying to use polymorphic relations to model a type hierarchy requires a lot of custom wiring and you lose the ability to query the parent type as a unified collection.

### Class Table Inheritance (this package)

Shared attributes live in a parent table, type-specific attributes live in separate subtype tables linked by foreign key.

- **Pros:** Properly normalized. No nullable columns, no duplicated columns, real foreign key constraints everywhere. You can query the parent type and get back a mixed collection of correctly-typed subtype instances. Subtype-specific queries auto-join as needed.
- **Cons:** More tables to manage. Reads require joins (handled automatically by the package). Writes touch multiple tables (wrapped in transactions by the package). Initial setup is a bit more involved than STI.

### When to use CTI

CTI is the right fit when your subtypes share an identity (a Quiz *is* an Assessment), share common attributes (title, timestamps), and each type also has its own attributes (passing_score, anonymous). If your types are unrelated entities that just happen to share a relationship, polymorphic relations are the better tool.

## Features

- Automatic model type resolution and instantiation
- Seamless saving/updating across parent and subtype tables
- Automatic batch-loading of subtype data (no N+1 queries)
- Support for Eloquent events and relationships
- Database-enforced referential integrity with real foreign keys

## Requirements

- PHP ^8.1
- Laravel 8.x - 13.x (`illuminate/database` >=8.0 <14.0)

## Installation

```bash
composer require pannella/laravel-cti
```

## Quick Start

CTI uses three layers of tables: an optional **lookup table** for type definitions, a **parent table** for shared columns, and one or more **subtype tables** for type-specific columns.

```php
// Parent table: shared attributes
Schema::create('assessments', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->foreignId('type_id')->constrained('assessment_types');
    $table->timestamps();
});

// Subtype table: quiz-specific attributes
Schema::create('assessment_quiz', function (Blueprint $table) {
    $table->unsignedBigInteger('assessment_id')->primary();
    $table->integer('passing_score')->nullable();
    $table->integer('time_limit')->nullable();
    $table->boolean('show_correct_answers')->default(false);

    $table->foreign('assessment_id')->references('id')->on('assessments')->onDelete('cascade');
});
```

```php
// Parent model
class Assessment extends Model
{
    use HasSubtypes;

    protected static $subtypeMap = [
        'quiz' => Quiz::class,
        'survey' => Survey::class,
    ];

    protected static $subtypeKey = 'type_id';
    protected static $subtypeLookupTable = 'assessment_types';
    protected static $subtypeLookupKey = 'id';
    protected static $subtypeLookupLabel = 'label';

    protected $fillable = ['title', 'type_id'];
}

// Subtype model
class Quiz extends SubtypeModel
{
    protected $table = 'assessments'; // must be the parent table
    protected $subtypeTable = 'assessment_quiz';
    protected $subtypeAttributes = ['passing_score', 'time_limit', 'show_correct_answers'];
    protected $ctiParentClass = Assessment::class;

    protected $fillable = ['passing_score', 'time_limit', 'show_correct_answers'];
}
```

```php
// Create
$quiz = Quiz::create([
    'title' => 'Final Exam',
    'passing_score' => 80,
    'time_limit' => 60,
]);

// Query (auto-joins subtype table when needed)
$hard = Quiz::where('passing_score', '>', 90)->get();

// Parent queries return correctly-typed subtype instances
$all = Assessment::all(); // mixed collection of Quiz, Survey, etc.
```

For the full setup guide including direct discriminator mode, PHP 8.1 attributes, and fillable/casts inheritance, see the [Getting Started](docs/getting-started.md) guide.

## Documentation

- [Getting Started](docs/getting-started.md) - Database schema, model setup, PHP attributes, fillable/casts inheritance
- [Configuration](docs/configuration.md) - Package configuration and missing subtype data handling
- [Querying](docs/querying.md) - CRUD operations, query builder auto-joins, mass updates, supported methods
- [Relationships](docs/relationships.md) - Subtype relationships, foreign key behavior, parent relationship inheritance
- [Events](docs/events.md) - Subtype model events
- [Internals](docs/internals.md) - Type resolution, caching, batch loading, save flow, known limitations
- [API Reference](docs/api-reference.md) - Full method and property reference for all classes and traits

## License

MIT
