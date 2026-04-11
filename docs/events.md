# Events

Subtype models fire additional events around subtype data persistence:

| Event | Fires | Halts on `false`? |
|-------|-------|-------------------|
| `subtypeSaving` | Before subtype data is saved | Yes |
| `subtypeSaved` | After subtype data is saved | No |
| `subtypeDeleting` | Before subtype data is deleted | Yes |
| `subtypeDeleted` | After subtype data is deleted | No |

Register listeners using the static methods:

```php
Quiz::subtypeSaving(function (Quiz $quiz) {
    // Validate or modify subtype data before save
    if ($quiz->passing_score > 100) {
        return false; // halts the save
    }
});

Quiz::subtypeSaved(function (Quiz $quiz) {
    // React after subtype data is saved
});

Quiz::subtypeDeleting(function (Quiz $quiz) {
    // Clean up before subtype data is deleted
});

Quiz::subtypeDeleted(function (Quiz $quiz) {
    // React after subtype data is deleted
});
```

Returning `false` from `subtypeSaving` or `subtypeDeleting` will halt the entire operation (including the parent table write).

You can also map these events to event classes via the `$dispatchesEvents` property:

```php
protected $dispatchesEvents = [
    'subtypeSaving' => QuizSaving::class,
];
```
