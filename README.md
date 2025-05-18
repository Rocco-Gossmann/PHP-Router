
# PHP - Router
A Small PHP-Lib providing Controller based Routing via PHP.
Controllers and Routes are defined via Attributes.

<!-- vim-markdown-toc GFM -->

* [Installation](#installation)
* [Usage](#usage)
    * [The ìndex.php](#the-ìndexphp)
        * [The /controllers Folder](#the-controllers-folder)
        * [The /controllers/_.php File](#the-controllers_php-file)
    * [Defining Controllers](#defining-controllers)
    * [Defining Routes](#defining-routes)
    * [Defining Expression Routes](#defining-expression-routes)

<!-- vim-markdown-toc -->

## Installation
Just copy the `Router.php` of this Repo into your project and include it.

## Usage


### The ìndex.php

Usually, this is the entrypoint to your application, and the first PHP-Script
to be executed.
```php
<?php
    require_once __DIR__ . "/Router.php";

    use rogoss\router\Router;

    (new Router(

        // Folder, where the Router will search for suitable routers
        __DIR__ . "/controllers",

        // The Default router, used to handle all calles to the none domain
        __DIR__ . "/controllers/_.php",

    ))->HandleRoute($_SERVER['REMOTE_URI']);
```

#### The /controllers Folder

This router handles SubDirectories as `controllers`

The controller is defined by the first Subdirectory of the url.
So you need to keep directory name limitations in mind, when defining your URLs

**Example**:
```php
GET office/index.hml => __DIR__ . "/controllers/office.php"
GET kitchen/coffemachine => __DIR__ . "/controllers/kitchen.php"
GET kitchen/coffemachine/coffee.html => __DIR__ . "/controllers/kitchen.php"
```

#### The /controllers/_.php File
If the requested URL does not contain a directory, the request will be handled
by the Controller defined in this file.


### Defining Controllers
As the already described, the Router uses the first directory of a URL to define the controller-file.
Inside that file a Class with the Attribute `#[RouterController]` should be part
of this File.

Inside that class, you define static methods that have one or more `RouterRoute` Attributes.


a basic controller file could look like this.
```php
<?php
    // `__DIR__ . "/controllers/kitchen.php"
    require_once __DIR__ . "/../Router.php";

    use rogoss\router\RouterController;
    use rogoss\router\RouterRoute;

    #[RouterController]
    class KitchenController {

        #[
            RouterRoute(""),  // calls to `/kitchen/`
            RouterRoute("index.html") // calls to `/kitchen/index.hml`
        ]
        public static function indexPage() {

            echo "<h1>Welcome to the Kitchen</h1>",
                "<br />",
                "<a href=\"coffeemachine/coffee.html\">",
                "Time for Coffee !!!",
                "</a>"
            ;

        }

        #[ RouterRoute("coffeemachine/coffee.html") ]
        public static function coffeemachineCoffeePage() {

            // this name does not matter, ----/\
            // because Attributes, but you may choose something, that
            // makes it easier to find for your editor

            echo "<h1>Coffeemachine => choose </h1>",
                "<br /> <a href=\"coffemachine/coffee/black\">Black</a>",
                "<br /> <a href=\"coffemachine/coffee/milk\">Milk</a>",
            ;

        }

    }
```
> [!warning]
> Since the first found directory identfies the controller, you can't invoke the controller
> by it's name alone.
>
> lets take `GET /kitchen` as an example. You would want this to be handled by
> `__DIR__ . "/controllers/kitchen.php"` => `RouterRoute("")`
>
> However, there is no directory in the URL `/kitchen`, while there is one in `/kitchen/`.
> `/kitchen` (without trailing `/`) will be handled by `_.php` (the default controller) instead.


### Defining Routes
Routes are `static` class functions identified by the `#[RouterRoute]` Attribute.

RouterMethods are given at least 2 Parameters.
```php
// A reference to the Router that is currently processing the Request
Router $router

// An array, containing matched route and, for expression-routed, the matched capture groups
string[] $matches
```

We can use these two to solve the problem, that routers can't be invoked by semselfs
without a trailing `/` in the url

in `_.php` (the default controller) define a route method, that redirects a call to
`office` and `kitchen` to their `office/` and `kitchen/` counterparts.
```php
    #[
        RouterRoute("office"),
        RouterRoute("kitchen")
    ]
    public static function redirectToControllerRoot(
        Router $router,
        array $matches
    ) {
        $router->HandleRoute("{$matches[0]}/"); // <- matches[0] is always the full matched path, notice that we add "/" to the end.
                                                // A slash marks that we target the controller, rather than a route
    }

```

### Defining Expression Routes
It is possible to define more dynamic routes, by using regular expressions.

```php
    #[
        RouterRoute( expression: "office-([0-9]+)(.*)" ),
    ]
    public static function redirectOfficeRoute(
        Router $router,
        array $matches
    ) {
        $officeid = $matches[1];
        $path = $match[2];
        $router->HandleRoute("office/{$officeid}{$path}");
    }

```
The above RouterRoute would match any route containing the word office, followed by a dash and a number:
```
office-1
...
office-34
...
office-99
...
```
Since expression routes follow RegularExpression / `preg_match` rules, and we put the number-match in parentheses `([0-9]+)`,
we can extract that number through the $matches Paramter, given to the Route Handler.



