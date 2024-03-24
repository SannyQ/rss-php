<?php

/**
 * RSS for PHP - A small and easy-to-use library for consuming an RSS Feed.
 *
 * @copyright  Copyright (c) 2008 David Grudl
 * @license    New BSD License
 * @version    1.5
 */
class Feed
{
    /** @var string Cache expiration time, defaults to '1 day'. */
    public static $cacheExpire = '1 day';

    /** @var string|null Directory to store the cache files. */
    public static $cacheDir = null;

    /** @var string User agent for the HTTP requests, defaults to 'FeedFetcher-Google'. */
    public static $userAgent = 'FeedFetcher-Google';

    /** @var SimpleXMLElement Holds the loaded XML feed. */
    protected $xml;

    /**
     * Loads RSS or Atom feed from a URL.
     *
     * Automatically detects the feed type and parses it accordingly.
     *
     * @param string $url The URL of the feed to load.
     * @param string|null $user Optional username for HTTP authentication.
     * @param string|null $pass Optional password for HTTP authentication.
     * @return Feed Returns an instance of Feed with the loaded data.
     * @throws FeedException Throws FeedException if the feed cannot be loaded or parsed.
     */
    public static function load($url, $user = null, $pass = null)
    {
        $xml = self::loadXml($url, $user, $pass);
        if ($xml->channel) {
            return self::fromRss($xml);
        } else {
            return self::fromAtom($xml);
        }
    }

    /**
     * Specifically loads an RSS feed.
     *
     * @param string $url The URL of the RSS feed.
     * @param string|null $user Optional username for HTTP authentication.
     * @param string|null $pass Optional password for HTTP authentication.
     * @return Feed Returns an instance of Feed with the RSS data loaded.
     * @throws FeedException Throws FeedException if the RSS feed cannot be loaded or parsed.
     */
    public static function loadRss($url, $user = null, $pass = null)
    {
        return self::fromRss(self::loadXml($url, $user, $pass));
    }

    /**
     * Specifically loads an Atom feed.
     *
     * @param string $url The URL of the Atom feed.
     * @param string|null $user Optional username for HTTP authentication.
     * @param string|null $pass Optional password for HTTP authentication.
     * @return Feed Returns an instance of Feed with the Atom data loaded.
     * @throws FeedException Throws FeedException if the Atom feed cannot be loaded or parsed.
     */
    public static function loadAtom($url, $user = null, $pass = null)
    {
        return self::fromAtom(self::loadXml($url, $user, $pass));
    }

    /**
     * Parses an RSS feed from a SimpleXMLElement.
     *
     * @param SimpleXMLElement $xml XML element representing the RSS feed.
     * @return Feed Returns an instance of Feed with the RSS data.
     * @throws FeedException Throws FeedException if the provided XML is not a valid RSS feed.
     */
    private static function fromRss(SimpleXMLElement $xml);

    /**
     * Parses an Atom feed from a SimpleXMLElement.
     *
     * @param SimpleXMLElement $xml XML element representing the Atom feed.
     * @return Feed Returns an instance of Feed with the Atom data.
     * @throws FeedException Throws FeedException if the provided XML is not a valid Atom feed.
     */
    private static function fromAtom(SimpleXMLElement $xml);

    /**
     * Generic method for accessing properties of the loaded feed.
     *
     * @param string $name The name of the property to access.
     * @return SimpleXMLElement The value of the specified property.
     */
    public function __get($name);

    /**
     * Prevents setting of properties.
     *
     * @param string $name The name of the property to set.
     * @param mixed $value The value to set the property to.
     * @return void
     * @throws Exception Throws an exception when trying to set a property.
     */
    public function __set($name, $value);

    /**
     * Converts the loaded XML feed into an array.
     *
     * @param SimpleXMLElement|null $xml Optional XML element to convert, defaults to the loaded feed.
     * @return array An associative array representation of the feed.
     */
    public function toArray(SimpleXMLElement $xml = null);

    /**
     * Loads XML from either the cache or via HTTP.
     *
     * Tries to fetch the XML from cache first, if available and not expired. Otherwise, it makes an HTTP request.
     *
     * @param string $url The URL of the feed.
     * @param string|null $user Optional username for HTTP authentication.
     * @param string|null $pass Optional password for HTTP authentication.
     * @return SimpleXMLElement The loaded XML.
     * @throws FeedException Throws FeedException if the XML cannot be loaded.
     */
    private static function loadXml($url, $user, $pass);

    /**
     * Sends an HTTP request to fetch the feed XML.
     *
     * Uses cURL if available, falls back to file_get_contents otherwise.
     *
     * @param string $url The URL to fetch.
     * @param string|null $user Optional username for HTTP authentication.
     * @param string|null $pass Optional password for HTTP authentication.
     * @return string|false The response body or false on failure.
     * @throws FeedException Throws FeedException if the request fails.
     */
    private static function httpRequest($url, $user, $pass);

    /**
     * Adjusts namespaces in the XML for easier access.
     *
     * @param SimpleXMLElement $el The element to adjust namespaces for.
     * @return void
     */
    private static function adjustNamespaces($el);
}

/**
 * Custom exception class for handling feed-related errors.
 */
class FeedException extends Exception
{
}
