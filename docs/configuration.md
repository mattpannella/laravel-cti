# Configuration

The package works out of the box with sensible defaults and no configuration is required. If you want to customize behavior, publish the config file:

```bash
php artisan vendor:publish --tag=cti-config
```

This creates `config/cti.php` in your application:

```php
return [
    // 'exception', 'null', or 'log' (default)
    'on_missing_subtype_data' => 'log',
];
```

## Missing Subtype Data Handling

When a parent record exists but its corresponding subtype row is missing (a data integrity issue), the `on_missing_subtype_data` option controls the behavior:

| Value | Behavior |
|-------|----------|
| `'log'` *(default)* | Subtype attributes remain `null` and a warning is logged |
| `'exception'` | Throws `SubtypeException` immediately |
| `'null'` | Subtype attributes silently remain `null` with no warning |

Under every strategy, `$model->isSubtypeDataMissing()` returns `true` when the subtype row is missing.

You can check for missing data programmatically:

```php
$quiz = Quiz::find(1);

if ($quiz->isSubtypeDataMissing()) {
    // Handle the data integrity issue
}
```
