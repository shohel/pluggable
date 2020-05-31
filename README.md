> If you like this library, place star ⭐ at this repository and my profile please.

Pluggable
================

With Pluggable, you can register your actions hooks filter hooks to your PHP Project like WordPress, no matter it's raw PHP project / Laravel / Codeigniter / Moodle LMS / or any others.  This is a library that allows you to make your PHP project so extensible.

Installation
------------

Use [Composer] to install the package:

```
$ composer require shohel/pluggable
```

Integration in Laravel
------------


Pluggable has great support for Laravel and comes with a Service Provider for easy integration. The vendor/autoload.php is included by Laravel, so you don't have to require or autoload manually. Just see the instructions below.

After you have installed Pluggable, open your Laravel config file config/app.php and add the following lines.

In the $providers array add the service providers for this package at the very first line.

```php
\Shohel\Pluggable\PluggableServiceProvider::class,
```

Now the Pluggable will be auto-loaded by Laravel.

Integration in Any other PHP Project
------------

Include composer autoload file to your project before bootsrap / entry point.

```php
// include composer autoload
require 'vendor/autoload.php';
```

Pluggable will do the rest.

Usage
------------

#### Action Hook

`do_action( string $tag, mixed $arg )`

Execute functions hooked on a specific action hook.

This function invokes all functions attached to action hook $tag. It is possible to create new action hooks by simply calling this function, specifying the name of the new hook using the $tag parameter.

Example usage:

```php
// The action callback function.
function example_callback( $arg1, $arg2 ) {
    // (maybe) do something with the args.
}
add_action( 'example_action', 'example_callback', 10, 2 );

/*
 * Trigger the actions by calling the 'example_callback()' function
 * that's hooked onto `example_action` above.
 *
 * - 'example_action' is the action hook.
 * - $arg1 and $arg2 are the additional arguments passed to the callback.
$value = do_action( 'example_action', $arg1, $arg2 );
```

#### Flter Hook

`apply_filters( string $tag, mixed $value )`

Calls the callback functions that have been added to a filter hook.

The callback functions attached to the filter hook are invoked by calling this function. This function can be used to create a new filter hook by simply calling this function with the name of the new hook specified using the $tag parameter.

Example usage:

```php
// The filter callback function.
function example_callback( $string, $arg1, $arg2 ) {
    // (maybe) modify $string.
    return $string;
}
add_filter( 'example_filter', 'example_callback', 10, 3 );

/*
 * Apply the filters by calling the 'example_callback()' function
 * that's hooked onto `example_filter` above.
 *
 * - 'example_filter' is the filter hook.
 * - 'filter me' is the value being filtered.
 * - $arg1 and $arg2 are the additional arguments passed to the callback.
$value = apply_filters( 'example_filter', 'filter me', $arg1, $arg2 );
```

#### Hooks call back from the class

You can reference a class method to hooks callback instead of function, use `['className','callbackMethod']` or use it within a class. `[$this,'getStuffDone'] ` 

Here is a better way of doing it:

```php
class MyClass {
     function __construct() {
          add_action( 'example_action',array( $this, 'callbackMethod' ) );
     }
     function callbackMethod() {
          // .. This is where stuff gets done ..
     }
}
$var = new MyClass();
```

As the hooks will work exactly like WordPress, you can read more about action hooks / filter hooks to WordPress.org documentation.

[Action Hooks](https://developer.wordpress.org/plugins/hooks/actions/) |
[Filter Hooks](https://developer.wordpress.org/plugins/hooks/filters/)


> If you like this library, place star ⭐ at this repository and my profile please.
