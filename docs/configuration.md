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
| `'log'` *(default)* | Subtype attributes remain `null`, a warning is logged, and `$model->isSubtypeDataMissing()` returns `true` |
| `'exception'` | Throws `SubtypeException` immediately |
| `'null'` | Subtype attributes silently remain `null` with no warning and no flag |

You can check for missing data programmatically:

```php
$quiz = Quiz::find(1);

if ($quiz->isSubtypeDataMissing()) {
    // Handle the data integrity issue
}
```
