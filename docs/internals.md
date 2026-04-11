# Internals

## Type Resolution

When a parent model is loaded from the database, the `HasSubtypes` trait's `newFromBuilder()` method reads the discriminator column, resolves the type label, and maps it to a subtype class via `$subtypeMap`. With a lookup table, the integer type ID is resolved to a label via a cached query. In direct discriminator mode (no lookup table), the column value is used as the label directly with no extra query. The returned model is an instance of the subtype class with all parent attributes set.

If the discriminator value doesn't exist in the lookup table, a `SubtypeException` is thrown. If the resolved label doesn't match any key in `$subtypeMap`, the base parent model instance is returned as-is (not morphed into a subtype).

## Batch Loading

Both the parent model (via `HasSubtypes`) and subtype models (via `SubtypeModel`) return a `SubtypedCollection` from `newCollection()`. The collection's constructor groups models by their concrete class and executes **one query per subtype table** to load all subtype data, eliminating N+1 queries.

For example, if you call `Assessment::all()` and the result contains 50 quizzes and 30 surveys, the collection runs two queries: one against `assessment_quiz` for all 50 quiz IDs and one against `assessment_survey` for all 30 survey IDs. Each model's subtype data is filled in using `forceFill()` and the original attributes are synced so the model appears "clean" (no dirty attributes from the fill).

If a model's subtype row is missing from the database, the collection calls `handleMissingSubtypeData()` on it, which follows the behavior configured in `on_missing_subtype_data` (see [Configuration](configuration.md)).

## Caching

The package uses several caching layers to avoid redundant queries within a request:

### Lookup Table Cache

When using a lookup table, the first call to `resolveSubtypeLabel()` batch-loads the entire lookup table into memory and caches it in the Laravel container using a key scoped to the connection, database, and table name. This means all subsequent type resolutions for that table are in-memory lookups. The container-scoped caching is safe for Laravel Octane and multi-tenant setups since each container instance has its own cache.

### Discriminator Scope Cache

The `SubtypeDiscriminatorScope` caches the resolved type ID for each subtype model class so the global scope doesn't re-query the lookup table on every query.

### Column Validation Cache

The first `save()` or `loadSubtypeData()` call for each model class triggers a schema query to verify that `$subtypeAttributes` don't overlap with parent table columns. This result is cached per-class in a static property for the rest of the request.

### Parent Property Cache

Parent `$fillable` and `$casts` values are resolved once per subtype class and cached in a static property so subsequent instances don't repeat the work.

### Clearing Caches

If you're adding type definitions at runtime (e.g., inserting rows into the lookup table during a seeder or migration), the cached type IDs may be stale. Two static methods are available to clear the caches:

```php
use Pannella\Cti\Support\SubtypeDiscriminatorScope;
use Pannella\Cti\Traits\BootsSubtypeModel;

// Clear the discriminator scope's type ID cache
SubtypeDiscriminatorScope::clearCache();

// Clear the creating-event type ID cache
BootsSubtypeModel::clearTypeIdCache();
```

You'd typically only need these in tests or seeders. In normal request handling, the caches are request-scoped and don't carry over.

## Column Overlap Validation

`$subtypeAttributes` must not contain any column names that also exist on the parent table. The package validates this automatically on the first `save()` or `loadSubtypeData()` call for each model class (one schema query per class, cached for the lifetime of the request). If an overlap is detected, a `SubtypeException` is thrown immediately with a clear message listing the conflicting columns.

**Why this restriction exists:** Internally, column names are the sole key used to route data between parent and subtype tables. Every operation (save, load, query) splits attributes by checking whether a column is in `$subtypeAttributes`. If the same column name exists in both tables:

- **Saves** would send the value only to the subtype table, leaving the parent column NULL or stale
- **Loads** would overwrite the parent's value with the subtype's value (which may be NULL) via `forceFill()`
- **Queries** would produce ambiguous SQL column references after the auto-join

This is a fundamental constraint of Eloquent's flat `$attributes` array. There's no way to store two values for the same key.

**Workaround:** Rename the subtype column with a prefix. For example, if both your parent table and subtype table have a `description` column, rename the subtype column to `quiz_description`:

```php
// Migration
Schema::table('assessment_quiz', function (Blueprint $table) {
    $table->renameColumn('description', 'quiz_description');
});

// Model
protected $subtypeAttributes = ['quiz_description', 'passing_score', 'time_limit'];
```

## Save Flow

When you call `save()` on a subtype model, the following happens inside a database transaction:

1. Parent attributes are saved first via the standard Eloquent `save()`. This ensures the primary key exists for new records.
2. Subtype attributes are saved to the subtype table using `upsert()`. This avoids a race condition between checking if the row exists and inserting it.
3. The model's attribute state is synced so it reflects the saved values from both tables.

If either write fails, the transaction is rolled back and nothing is persisted.

On `delete()`, the subtype row is hard-deleted first, then the parent row. This is also wrapped in a transaction. Note that subtype deletes are always hard deletes, regardless of whether the parent uses `SoftDeletes` (see [Known Limitations](#soft-deletes) below).

## Replicate and Refresh

`replicate()` copies both parent and subtype attributes into a new unsaved model instance. The subtype foreign key is automatically excluded from the copy so you can save the replica as a new record without primary key conflicts.

```php
$original = Quiz::find(1);
$copy = $original->replicate();
$copy->title = 'Copy of ' . $original->title;
$copy->save(); // creates new rows in both tables
```

`refresh()` reloads both parent attributes (via Eloquent's built-in `refresh()`) and subtype data from the database. If the record has been deleted between load and refresh, subtype data is not reloaded.

## Serialization

`toArray()` and `toJson()` include both parent and subtype attributes in a flat structure. There's no nesting or separation between parent and subtype data in the output:

```php
$quiz = Quiz::find(1);
$quiz->toArray();
// [
//     'id' => 1,
//     'title' => 'Final Exam',          // parent attribute
//     'type_id' => 1,                    // parent attribute
//     'created_at' => '...',             // parent attribute
//     'updated_at' => '...',             // parent attribute
//     'passing_score' => 80,             // subtype attribute
//     'time_limit' => 60,                // subtype attribute
//     'show_correct_answers' => false,   // subtype attribute
// ]
```

This means API resources, JSON responses, and anything that calls `toArray()` will include everything without any extra configuration. If you need to hide specific attributes, use Eloquent's `$hidden` property as usual.

## Known Limitations

### `cursor()` and `lazy()` Bypass Batch Loading

`cursor()` and `lazy()` iterate models individually and bypass `newCollection()`, so subtype data is **not** automatically loaded. Call `loadSubtypeData()` manually:

```php
Quiz::cursor()->each(function (Quiz $quiz) {
    $quiz->loadSubtypeData();
    // now $quiz->passing_score is available
});
```

This makes a separate query per model, so be mindful of this if you're iterating over a large number of records.

### Soft Deletes

Soft deletes are not handled on subtype tables. The parent table can use `SoftDeletes` normally, but the subtype row will remain even when the parent is soft-deleted. Restoring a soft-deleted parent works without issues since the subtype row was never removed. However, this means the subtype row stays in the database for the entire duration of the soft delete, which may matter if you have unique constraints or other logic on the subtype table.

If you need to clean up subtype rows when a parent is soft-deleted, you can listen for the Eloquent `deleting` event on the parent model or use the `subtypeDeleting` event on the subtype model to handle it manually.
