Agents Rules: PHP Library & Package Development

## 1. Core Philosophy & Constraints
- **PHP Version Target:** Minimum compatibility is PHP 8.3+. Use modern features (constructor promotion, readonly classes, enums, match expressions) but do not use features from versions above the target.
- **Strict Typing:** Every single PHP file MUST begin with `declare(strict_types=1);` as the absolute first line of code.
- **Zero-Dependency Goal:** Minimize third-party packages in `composer.json`. Rely on native PHP extensions or standard library features whenever possible.
- **TDD – Follow TDD design:** Write tests before writing code, ensuring that each test fails initially before it passes.
- **Explicit dependency version:** Set the version explicitly in `composer.json` for all dependencies.

## 2. Standards & Style (PSR Compliance)
- **PSR-12 / PER Coding Style:** Follow the modern PHP Code Style standards strictly.
- **PSR-4 Autoloading:** All source code lives in `src/` under the root namespace (e.g., `Vendor\PackageName\`). All tests live in `tests/`.
- **PSR-3 / PSR-11 Integration:** If the library requires logging or a container, strictly type-hint against PSR interfaces (`Psr\Log\LoggerInterface`), never concrete implementations.

## 3. Security 
- **Input Validation:** Validate all user input to prevent injection attacks (e.g., SQL injection, XSS). Use prepared statements or parameterized queries for database interactions.
- **Error Handling:** Implement proper error handling to prevent sensitive information leakage. Use try-catch blocks to catch and handle exceptions gracefully.
- **Prevent sensible data exposure:** Avoid logging or displaying sensitive information in error messages or logs. In the case of parameters inside methods/functions that pass sensitive data, ensure to use `#[\SensitiveParameter]`.

## 4. API Design & Code Quality
- **Type Hinting:** Every method argument and return value must be explicitly typed. Use union types (`string|int`) or intersection types where appropriate. Avoid `mixed` unless absolutely necessary.
- **Immutability:** Favor immutability. Use `readonly` for classes or properties that should not change after instantiation. If a state change is needed, provide a `withX()` method that returns a new instance.
- **Visibility:** Default to `private` or `protected` for internal properties and methods. Keep the public API surface as small and focused as possible.

## 5. Documentation & PHPDoc
- **Clear Contracts:** Every public method must have a PHPDoc block detailing its purpose.
- **Annotations:** Use `@param`, `@return`, and `@throws` explicitly if the native PHP types cannot fully express the type (e.g., generics style arrays: `array<int, User>`).
- **Static Analysis:** Write code that passes strict static analysis rules for PHPStan / Psalm (Level 8+).

## 6. Error Handling & Exceptions
- **Domain Exceptions:** Never throw generic `\Exception`. Create specific domain exceptions for the library extending `\RuntimeException` or `\InvalidArgumentException`.
- **Interface-driven Exceptions:** Implement a marker interface for all library exceptions (e.g., `interface ExceptionInterface`), allowing consumers to catch all errors from this package using a single `catch` block.
- **Centralize Exception Handling:** Handle exceptions centrally in a single place, such as a dedicated error handler or middleware, to ensure consistent error handling across the library.

## 7. File Headers & Licensing
- EVERY new PHP file MUST start exactly with the following template (adapted with the current year and package details):

```php
<?php

declare(strict_types=1);

/*
 * This file is part of the Peav package.
 *
 * (c) WireUpDev <wireupdev@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
```

## 7. Output Format
- Provide clean PHP code that complies with the rules above.
- Do not output lengthy explanations.
- When creating or modifying a feature, always provide the corresponding PHPUnit test case for `tests/`.


 



