# Running the tests

This suite uses [PHPUnit](https://phpunit.de/) plus [WP_Mock](https://github.com/10up/wp_mock),
a library that fakes WordPress's own functions in memory. That means these
tests run in plain PHP, in a few seconds, with **no WordPress install, no
database, and no web server** -- you're testing this plugin's own logic in
isolation, the same way you'd unit test a normal PHP/Python/C++ library.

## One-time setup on Ubuntu

```bash
# PHP + the extensions Composer/PHPUnit need, and Composer itself
sudo apt update
sudo apt install php-cli php-mbstring php-xml unzip
sudo apt install composer   # or see https://getcomposer.org/download/ for the latest version

# From inside the docsplorer/ plugin folder:
composer install
```

`composer install` downloads PHPUnit, WP_Mock, and Mockery into a local
`vendor/` folder (already excluded via `.gitignore` -- don't commit it).

On a normal Ubuntu user account, just `composer install` is all you need.
You only need to prefix it with `COMPOSER_ALLOW_SUPERUSER=1` if you're
running as root (e.g. inside some Docker containers) -- Composer refuses
to run its plugins as root otherwise.

## Running the tests

```bash
vendor/bin/phpunit
```

or, using the Composer script alias defined in `composer.json`:

```bash
composer test
```

You should see something like:

```
PHPUnit 9.6.x

...........................                                     27 / 27 (100%)

Time: 00:00.05, Memory: 8.00 MB

OK (27 tests, 41 assertions)
```

## What this suite covers (and what it doesn't)

Covered, with real unit tests:

- `includes/helpers.php` -- the file-extension-to-badge mapping and the SVG
  icon builders.
- `includes/shortcode.php` -- the breadcrumb, subfolder, and document
  rendering functions, including regression tests for the two bugs we found
  and fixed together (a folder's documents leaking into its parent folder,
  and the "No documents in this folder" message showing even when the
  folder had subfolders).

Not covered yet:

- `includes/post-type.php` and `includes/admin-manager.php` -- these lean
  heavily on things like `$_FILES`, AJAX nonces, and WordPress's media
  upload pipeline, which need a fair bit more WP_Mock setup (or a full
  WordPress test environment) to test meaningfully. This is a reasonable
  next step once the current suite feels familiar -- ask if you'd like a
  hand extending it in that direction.

## How this works, in a nutshell

Each test:

1. Uses `WP_Mock::userFunction('some_wp_function')->andReturn(...)` to tell
   WP_Mock what a WordPress function should return when the plugin calls
   it -- similar in spirit to `unittest.mock.patch()` in Python, or a
   Google Mock `EXPECT_CALL` in C++.
2. Calls the real Docsplorer function directly (no WordPress running).
3. Asserts on what that function returned.

`tests/bootstrap.php` loads the plugin's `includes/*.php` files once,
after stubbing out the handful of WordPress functions they call directly
at the top of the file (`add_action`, `register_post_type`, etc.) so those
files can load without needing a real WordPress to register anything with.
