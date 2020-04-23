Charcoal Search
===============

[![License][badge-license]][charcoal-contrib-search]
[![Latest Stable Version][badge-version]][charcoal-contrib-search]

A [Charcoal][charcoal-app] service provider my cool feature.



## Table of Contents

-   [Installation](#installation)
    -   [Dependencies](#dependencies)
-   [Configuration](#configuration)
-   [Usage](#usage)
-   [Development](#development)
    -  [API Documentation](#api-documentation)
    -  [Development Dependencies](#development-dependencies)
    -  [Coding Style](#coding-style)
-   [Credits](#credits)
-   [License](#license)



## Installation

The preferred (and only supported) method is with Composer:

```shell
$ composer require locomotivemtl/charcoal-contrib-search
```



### Dependencies

#### Required

-   [**PHP 5.6+**](https://php.net): _PHP 7_ is recommended.
-   [**guzzlehttp/guzzle**](https://github.com/guzzle/guzzle): ~0.6
-   [**charcoal-admin**](https://github.com/locomotivemtl/charcoal-admin): >=0.14
-   [**charcoal-contrib-sitemap**](https://github.com/locomotivemtl/charcoal-contrib-sitemap): >=0.1.5


## Configuration

In your project's config file, require the search module : 
```json
{
    "modules": {
        "charcoal/search/search": {}
    }
}
```

## Usage

The module adds a `search` route action using GET. You may access http://project-url.com/search?keyword=[keyword].
You won't get any results until you run the IndexContent Script.


Before running the script, you need to setup 

```
// Once a day at midnight
// You need to precise the base URL as it won'T be provided by the cli
0 0 * * * cd /[project]/web && /usr/local/bin/php /[project]/web/vendor/bin/charcoal admin/search/index-content -u http://project-url.com/
```

### Parameters
- `-u` (--url) Website URL. Necessary as this is run in a cron script.
- `-c` (--config) Sitemap builder key (Defaults to `xml`)
- `-n` (--no_index_class) Class to filter out content from the crawled pages. Defaults to "php_no-index"
- `-i` (--index_element_id) ID of the element to be indexed in the crawled page. Defaults to entire page body.

## Development


### API Documentation

-   The auto-generated `phpDocumentor` API documentation is available at:  
    [https://locomotivemtl.github.io/charcoal-contrib-search/docs/master/](https://locomotivemtl.github.io/charcoal-contrib-search/docs/master/)
-   The auto-generated `apigen` API documentation is available at:  
    [https://codedoc.pub/locomotivemtl/charcoal-contrib-search/master/](https://codedoc.pub/locomotivemtl/charcoal-contrib-search/master/index.html)


### Development Dependencies

-   [php-coveralls/php-coveralls][phpcov]
-   [phpunit/phpunit][phpunit]
-   [squizlabs/php_codesniffer][phpcs]



### Coding Style

The charcoal-contrib-search module follows the Charcoal coding-style:

-   [_PSR-1_][psr-1]
-   [_PSR-2_][psr-2]
-   [_PSR-4_][psr-4], autoloading is therefore provided by _Composer_.
-   [_phpDocumentor_](http://phpdoc.org/) comments.
-   [phpcs.xml.dist](phpcs.xml.dist) and [.editorconfig](.editorconfig) for coding standards.

> Coding style validation / enforcement can be performed with `composer phpcs`. An auto-fixer is also available with `composer phpcbf`.


## Credits

-   [Locomotive](https://locomotive.ca/)


## License

Charcoal is licensed under the MIT license. See [LICENSE](LICENSE) for details.


[charcoal-contrib-search]:  https://packagist.org/packages/locomotivemtl/charcoal-contrib-search
[charcoal-app]:             https://packagist.org/packages/locomotivemtl/charcoal-app

[dev-scrutinizer]:    https://scrutinizer-ci.com/g/locomotivemtl/charcoal-contrib-search/
[dev-coveralls]:      https://coveralls.io/r/locomotivemtl/charcoal-contrib-search
[dev-travis]:         https://travis-ci.org/locomotivemtl/charcoal-contrib-search

[badge-license]:      https://img.shields.io/packagist/l/locomotivemtl/charcoal-contrib-search.svg?style=flat-square
[badge-version]:      https://img.shields.io/packagist/v/locomotivemtl/charcoal-contrib-search.svg?style=flat-square

[psr-1]:  https://www.php-fig.org/psr/psr-1/
[psr-2]:  https://www.php-fig.org/psr/psr-2/
[psr-3]:  https://www.php-fig.org/psr/psr-3/
[psr-4]:  https://www.php-fig.org/psr/psr-4/
[psr-6]:  https://www.php-fig.org/psr/psr-6/
[psr-7]:  https://www.php-fig.org/psr/psr-7/
[psr-11]: https://www.php-fig.org/psr/psr-11/
