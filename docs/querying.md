# Querying

## Basic Usage

Subtype data is loaded automatically whenever models are fetched via `get()`, `paginate()`, `find()`, `all()`, etc.

Subtype models automatically filter queries by their discriminator value. For example, `Quiz::all()` only returns records where `type_id` matches the quiz type. It will not return surveys. The parent model (`Assessment::all()`) returns all records and morphs them into the correct subtype instances.

```php
// Querying subtype models only returns records of that type
$quizzes = Quiz::all();     // Only quizzes (type_id = 1)
$surveys = Survey::all();   // Only surveys (type_id = 2)

// Parent model returns ALL records, morphed into correct subtype instances
$assessments = Assessment::all();  // Returns Quiz and Survey instances

// Each model is an instance of the correct subtype class
$assessments->first() instanceof Quiz; // true or false depending on type_id

// You can remove the discriminator filter if needed
$allRecords = Quiz::withoutGlobalScope(\Pannella\Cti\Support\SubtypeDiscriminatorScope::class)->get();

// Pagination works seamlessly; subtype data is batch-loaded for the page
$quizzes = Quiz::paginate(15);  // Only quizzes

// Create new subtype instance
$quiz = new Quiz();
$quiz->title = 'Final Exam';        // parent attribute
$quiz->passing_score = 80;          // subtype attribute
$quiz->save();                      // saves to both tables in a transaction

// Or use mass assignment
$quiz = Quiz::create([
    'title' => 'Final Exam',
    'passing_score' => 80,
    'time_limit' => 60,
]);

// Load single instance
$quiz = Quiz::find(1);              // hydrates both parent and subtype data

// Update existing
$quiz->time_limit = 60;
$quiz->save();                      // updates only modified tables

// Delete: removes subtype row first, then parent row
$quiz->delete();
```

## Query Builder

The `SubtypeQueryBuilder` automatically joins the subtype table whenever a subtype column is referenced in a query. No manual joins needed.

```php
// Auto-joins assessment_quiz table because passing_score is a subtype attribute
$quizzes = Quiz::where('passing_score', '>', 70)->get();

// Chain multiple conditions across both tables
$quizzes = Quiz::where('passing_score', '>', 70)
    ->where('title', 'like', '%Final%')   // parent column, no join needed
    ->orderBy('time_limit', 'desc')        // subtype column, join added once
    ->get();

// Aggregates work too
$avg = Quiz::avg('passing_score');
```

### How Joins Work

When you reference a subtype column in a query, the builder adds a **LEFT JOIN** between the parent table and the subtype table. A LEFT JOIN (rather than an INNER JOIN) is used so that parent records are not dropped from results if their subtype row is missing. This means subtype attributes may come back as `NULL` in that scenario, which ties into the [missing subtype data](configuration.md#missing-subtype-data-handling) configuration.

The join is only added once per query, regardless of how many subtype columns you reference. If you only query parent columns, no join is added at all.

## Mass Updates

Mass `update()`, `increment()`, and `decrement()` calls automatically split values between the parent and subtype tables.

### update()

When you pass a mix of parent and subtype columns to `update()`, the package splits them and runs separate updates on each table:

```php
// Updates title in the parent table and passing_score in the subtype table
Quiz::where('passing_score', '<', 60)->update([
    'title' => 'Remedial Quiz',       // parent column
    'passing_score' => 60,             // subtype column
]);
```

For the subtype update, the builder first collects the IDs of matching parent rows, then updates the subtype table using those IDs. If you're only updating parent columns, the subtype table is not touched, and vice versa.

### increment() and decrement()

These also handle the parent/subtype split. The `$extra` parameter (additional columns to update alongside the increment) is split between tables as well:

```php
// Increment a subtype column
Quiz::where('time_limit', '<', 120)->increment('time_limit', 10);

// Increment a subtype column and update a parent column at the same time
Quiz::where('passing_score', '<', 100)->increment('passing_score', 5, [
    'title' => 'Updated Quiz',   // parent column, updated separately
]);
```

When the incremented column is a subtype attribute, the builder collects matching IDs, increments the subtype column in the subtype table, and applies any parent extra values to the parent table separately.

## Supported Methods

The following query builder methods support automatic subtype joins:

| Method | Example |
|--------|---------|
| `where` / `orWhere` | `Quiz::where('passing_score', '>', 70)` |
| `whereIn` / `orWhereIn` | `Quiz::whereIn('passing_score', [70, 80, 90])` |
| `whereNotIn` / `orWhereNotIn` | `Quiz::whereNotIn('time_limit', [30, 60])` |
| `whereNull` / `orWhereNull` | `Quiz::whereNull('time_limit')` |
| `whereNotNull` / `orWhereNotNull` | `Quiz::whereNotNull('passing_score')` |
| `whereColumn` / `orWhereColumn` | `Quiz::whereColumn('passing_score', '>', 'time_limit')` |
| `whereBetween` / `orWhereBetween` | `Quiz::whereBetween('passing_score', [60, 100])` |
| `whereNotBetween` / `orWhereNotBetween` | `Quiz::whereNotBetween('passing_score', [0, 50])` |
| `whereDate` / `whereYear` / `whereMonth` / `whereDay` | `Quiz::whereDate('created_at', '2024-01-01')` |
| `whereJsonContains` / `whereJsonLength` / `whereJsonDoesntContain` | `Quiz::whereJsonContains('metadata->tags', 'math')` |
| `orderBy` / `orderByDesc` | `Quiz::orderBy('time_limit')` |
| `latest` / `oldest` | `Quiz::latest('time_limit')` |
| `groupBy` | `Quiz::groupBy('passing_score')` |
| `having` | `Quiz::groupBy('passing_score')->having('passing_score', '>', 70)` |
| `select` / `addSelect` | `Quiz::select('title', 'passing_score')` |
| `pluck` / `value` | `Quiz::pluck('passing_score')` |
| `update` (mass) | `Quiz::where('passing_score', '<', 60)->update(['passing_score' => 60])` |
| `increment` / `decrement` | `Quiz::where('passing_score', '<', 100)->increment('passing_score', 5)` |
| Aggregates | `Quiz::avg('passing_score')`, `Quiz::sum('time_limit')`, etc. |

Any query builder method that accepts a column name and is routed through the `__call` proxy will also trigger a join if the column is a subtype attribute. The methods listed above are the ones with explicit handling, but the builder has a generic fallback that covers additional methods like `orWhereDate`, `orWhereBetween`, etc.
