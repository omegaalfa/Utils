# Utils

A comprehensive PHP utility package providing common helper functions for string manipulation, array operations, validation, and date/time handling.

## Requirements

- PHP 8.1 or higher

## Installation

Install via Composer:

```bash
composer require omegaalfa/utils
```

## Features

- **String Utilities**: Manipulation, case conversion, slug generation, and more
- **Array Utilities**: Advanced array operations with dot notation support
- **Validators**: Common validation rules for email, URL, IP addresses, etc.
- **Date Utilities**: Date/time manipulation and formatting

## Usage

### String Utilities (Str)

```php
use Omegaalfa\Utils\Str;

// String case conversion
Str::camelCase('hello_world');           // 'helloWorld'
Str::snakeCase('helloWorld');            // 'hello_world'
Str::kebabCase('helloWorld');            // 'hello-world'
Str::studlyCase('hello_world');          // 'HelloWorld'

// String manipulation
Str::slug('Hello World');                // 'hello-world'
Str::limit('Hello World', 5);            // 'Hello...'
Str::upper('hello');                     // 'HELLO'
Str::lower('HELLO');                     // 'hello'
Str::title('hello world');               // 'Hello World'

// String search and replace
Str::contains('Hello World', 'World');   // true
Str::startsWith('Hello', 'He');          // true
Str::endsWith('World', 'ld');            // true
Str::before('Hello World', ' World');    // 'Hello'
Str::after('Hello World', 'Hello ');     // 'World'
Str::replaceFirst('a', 'b', 'aaa');      // 'baa'
Str::replaceLast('a', 'b', 'aaa');       // 'aab'

// Generate random string
Str::random(16);                         // Random 16-character string
```

### Array Utilities (Arr)

```php
use Omegaalfa\Utils\Arr;

$array = [
    'user' => [
        'name' => 'John',
        'email' => 'john@example.com'
    ]
];

// Dot notation support
Arr::get($array, 'user.name');                    // 'John'
Arr::get($array, 'user.age', 25);                 // 25 (default)
Arr::set($array, 'user.age', 30);                 // Sets user.age to 30
Arr::has($array, 'user.name');                    // true
Arr::forget($array, 'user.email');                // Removes user.email

// Array filtering
Arr::only($array, ['name', 'email']);             // Get only specified keys
Arr::except($array, ['password']);                // Get all except specified keys
Arr::where($array, fn($v) => $v > 10);            // Filter by callback

// Array utilities
Arr::first([1, 2, 3]);                            // 1
Arr::first([1, 2, 3], fn($v) => $v > 1);          // 2
Arr::last([1, 2, 3]);                             // 3
Arr::flatten([1, [2, [3, 4]]]);                   // [1, 2, 3, 4]
Arr::pluck([['name' => 'John']], 'name');         // ['John']
Arr::shuffle([1, 2, 3]);                          // Randomly shuffled array
```

### Validation (Validator)

```php
use Omegaalfa\Utils\Validator;

// Email and URL validation
Validator::email('test@example.com');             // true
Validator::url('https://example.com');            // true

// IP validation
Validator::ip('192.168.1.1');                     // true
Validator::ipv4('192.168.1.1');                   // true
Validator::ipv6('2001:db8::1');                   // true

// String validation
Validator::alpha('abc');                          // true
Validator::alphaNumeric('abc123');                // true
Validator::alphaDash('abc-123_def');              // true
Validator::numeric('123');                        // true

// Length and range validation
Validator::length('hello', 3, 10);                // true (between 3-10 chars)
Validator::min('hello', 3);                       // true (>= 3 chars)
Validator::max('hello', 10);                      // true (<= 10 chars)
Validator::between(5, 1, 10);                     // true (1 <= 5 <= 10)

// Value validation
Validator::in('apple', ['apple', 'banana']);      // true
Validator::notIn('grape', ['apple', 'banana']);   // true
Validator::regex('abc', '/^[a-z]+$/');            // true

// Format validation
Validator::json('{"name":"John"}');               // true
Validator::date('2024-01-01');                    // true
Validator::date('01/01/2024', 'm/d/Y');           // true
```

### Date/Time Utilities (Date)

```php
use Omegaalfa\Utils\Date;

// Create and parse dates
$now = Date::now();
$date = Date::parse('2024-01-01');
$date = Date::fromTimestamp(1704067200);

// Format dates
Date::format($date, 'Y-m-d');                     // '2024-01-01'
Date::timestamp($date);                           // Unix timestamp

// Date calculations
$date1 = new DateTime('2024-01-01');
$date2 = new DateTime('2024-01-10');

Date::diff($date1, $date2);                       // DateInterval object
Date::diffInDays($date1, $date2);                 // 9
Date::diffInHours($date1, $date2);                // 216
Date::diffInMinutes($date1, $date2);              // 12960

// Add/subtract time
Date::addDays($date, 5);                          // Add 5 days
Date::addHours($date, 2);                         // Add 2 hours
Date::addMinutes($date, 30);                      // Add 30 minutes

// Day boundaries
Date::startOfDay($date);                          // Set time to 00:00:00
Date::endOfDay($date);                            // Set time to 23:59:59

// Date comparisons
Date::isToday($date);                             // true/false
Date::isPast($date);                              // true/false
Date::isFuture($date);                            // true/false
Date::isBetween($date, $start, $end);             // true/false
```

## Testing

Run the test suite:

```bash
composer install
./vendor/bin/phpunit
```

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Author

- **omegaalfa**

## Support

If you find this package useful, please consider giving it a star on GitHub!
