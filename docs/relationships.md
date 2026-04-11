# Relationships

## Subtype Relationships

Define relationships that use the subtype's foreign key with the convenience methods provided by the `HasSubtypeRelations` trait (included in `SubtypeModel`):

```php
class Quiz extends SubtypeModel
{
    protected $table = 'assessments';
    protected $subtypeTable = 'assessment_quiz';
    protected $subtypeAttributes = ['passing_score', 'time_limit', 'show_correct_answers'];
    protected $ctiParentClass = Assessment::class;

    public function questions(): HasMany
    {
        // FK defaults to $subtypeKeyName (the subtype table's FK column)
        return $this->subtypeHasMany(Question::class);
    }

    public function gradingRubric(): HasOne
    {
        return $this->subtypeHasOne(GradingRubric::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->subtypeBelongsTo(Instructor::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->subtypeBelongsToMany(Tag::class);
    }
}
```

### Available Methods

| Method | Description |
|--------|-------------|
| `subtypeHasOne($related, $foreignKey?, $localKey?)` | One-to-one from subtype |
| `subtypeHasMany($related, $foreignKey?, $localKey?)` | One-to-many from subtype |
| `subtypeBelongsTo($related, $foreignKey?, $ownerKey?)` | Inverse one-to-one/many |
| `subtypeBelongsToMany($related, $table?, $foreignPivotKey?, $relatedPivotKey?, $parentKey?, $relatedKey?)` | Many-to-many from subtype |

All of these default the foreign key to `$subtypeKeyName` (which itself defaults to the parent model's primary key name).

### Foreign Key Behavior

The `subtype*` methods exist because a subtype model's `$table` is set to the parent table, so standard Eloquent relationship methods would derive foreign keys from the parent table name. The `subtype*` methods override this to use `$subtypeKeyName` instead.

For `subtypeHasOne` and `subtypeHasMany`, the foreign key on the related table defaults to `$subtypeKeyName`. For `subtypeBelongsTo`, the foreign key on the *subtype's own table* defaults to `$subtypeKeyName`. For `subtypeBelongsToMany`, the foreign pivot key defaults to `$subtypeKeyName`.

In practice, this usually means all your foreign keys will be `assessment_id` (or whatever your parent table's primary key is named), which is the same value you'd use with standard Eloquent. The difference is that the `subtype*` methods derive this automatically from the CTI configuration rather than from the table name.

## Parent Relationship Inheritance

Relationships defined on the parent model are automatically accessible from subtype instances. When you call a method on a subtype that doesn't exist on `SubtypeModel`, the `__call` proxy checks whether the parent CTI class has that method. If it does, a parent model instance is created, its state is transferred from the subtype, and the method is called on the parent.

```php
// Defined on Assessment (parent)
class Assessment extends Model
{
    use HasSubtypes;

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

// Accessible from Quiz (subtype) without redefining it
$quiz = Quiz::find(1);
$quiz->tags;    // proxied to Assessment::tags()
$quiz->creator; // proxied to Assessment::creator()
```

This means you don't need to duplicate parent relationships on each subtype. Define shared relationships on the parent model and they'll be available on all subtypes.

## Standard Eloquent Alternative

You can also use standard Eloquent relationship methods with an explicit foreign key instead of the `subtype*` convenience methods:

```php
public function questions(): HasMany
{
    return $this->hasMany(Question::class, 'assessment_id');
}
```

This works fine as long as you pass the foreign key explicitly, since Eloquent would otherwise derive it from `$table` (the parent table name), which may not match what you expect.
