# RSS & Atom Feeds for PHP
========================

[![Latest Stable Version](https://img.shields.io/badge/stable-v1.6-green)](https://github.com/SannyQ/rss-php/releases)
[![License](https://img.shields.io/badge/license-New%20BSD-blue.svg)](https://github.com/SannyQ/rss-php/blob/master/license.md)

RSS & Atom Feeds for PHP is a very small and easy-to-use library for consuming an RSS and Atom feeds, updated to include support for Guzzle and other optimizations.

## Requirements

- PHP 5.3 or newer with CURL extension or enabled `allow_url_fopen`.
- Licensed under the New BSD License.

## Installation

You can obtain the latest version from our [GitHub repository](https://github.com/SannyQ/rss-php/releases) or install it via Composer:


```
composer require SannyQ/rss-php
```

[Support Me](https://github.com/sponsors/dg)
--------------------------------------------

Do you like RSS? Are you looking forward to the new features?

[![Buy me a coffee](https://files.nette.org/icons/donation-3.svg)](https://github.com/sponsors/dg)

Thank you!


Usage
-----

Download RSS feed from URL:

```php
$rss = Feed::loadRss($url);
```

The returned properties are SimpleXMLElement objects. Extracting
the information from the channel is easy:

```php
echo 'Title: ', $rss->title;
echo 'Description: ', $rss->description;
echo 'Link: ', $rss->url;

foreach ($rss->item as $item) {
	echo 'Title: ', $item->title;
	echo 'Link: ', $item->url;
	echo 'Timestamp: ', $item->timestamp;
	echo 'Description ', $item->description;
	echo 'HTML encoded content: ', $item->{'content:encoded'};
}
```

Download Atom feed from URL:

```php
$atom = Feed::loadAtom($url);
```

You can also enable caching:

```php
Feed::$cacheDir = __DIR__ . '/tmp';
Feed::$cacheExpire = '5 hours';
```

You can setup a User-Agent if needed:

```php
Feed::$userAgent = "FeedFetcher-Google; (+http://www.google.com/feedfetcher.html)";
```

If you like it, **[please make a donation now](https://nette.org/make-donation?to=rss-php)**. Thank you!
