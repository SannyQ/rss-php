<?php

/**
 * RSS for PHP - A small and easy-to-use library for consuming an RSS Feed.
 *
 * @copyright  Copyright (c) 2008 David Grudl
 * @license    New BSD License
 * @version    1.6
 * @modified   by SannyQ > 2024-03-24 Added support guzzle and code optimizations
 */
class Feed
{
	/** @var int Cache expiration time in seconds or a strtotime compatible string */
	public static $cacheExpire = '1 day';

	/** @var string Directory to store cache files */
	public static $cacheDir;

	/** @var string Default User-Agent for HTTP requests */
	public static $userAgent = 'FeedFetcher-Google';

	/** @var SimpleXMLElement XML element containing feed data */
	protected $xml;

    /**
     * Loads RSS or Atom feed.
     * Attempts to load and parse a feed URL into a SimpleXMLElement.
     * @param string $url The feed URL
     * @param string|null $user Optional username for HTTP authentication
     * @param string|null $pass Optional password for HTTP authentication
     * @return Feed An instance of the Feed class
     * @throws FeedException if the feed cannot be loaded or parsed
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
     * Loads RSS feed.
     * @param string $url RSS feed URL
     * @param string|null $user Optional username
     * @param string|null $pass Optional password
     * @return Feed An instance of the Feed class
     * @throws FeedException if the RSS feed cannot be loaded
     */
	public static function loadRss($url, $user = null, $pass = null)
	{
		return self::fromRss(self::loadXml($url, $user, $pass));
	}


    /**
     * Loads Atom feed.
     * @param string $url Atom feed URL
     * @param string|null $user Optional username
     * @param string|null $pass Optional password
     * @return Feed An instance of the Feed class
     * @throws FeedException if the Atom feed cannot be loaded
     */
	public static function loadAtom($url, $user = null, $pass = null)
	{
		return self::fromAtom(self::loadXml($url, $user, $pass));
	}

	private static function fromRss(SimpleXMLElement $xml)
	{
		try {
			if (!$xml->channel) {
				throw new FeedParsingException('Invalid RSS feed format.');
			}

			self::adjustNamespaces($xml);

			foreach ($xml->channel->item as $item) {
				self::adjustNamespaces($item); // converts namespaces to dotted tags
				$item->url = (string) $item->link;
				$item->timestamp = isset($item->{'dc:date'}) ? strtotime($item->{'dc:date'}) : (isset($item->pubDate) ? strtotime($item->pubDate) : null);
			}
			return new self($xml->channel);
		} catch (\Exception $e) {
			throw new FeedParsingException('Failed to process RSS feed: ' . $e->getMessage());
		}
	}

	private static function fromAtom(SimpleXMLElement $xml)
	{
		if (
			!in_array('http://www.w3.org/2005/Atom', $xml->getDocNamespaces(), true)
			&& !in_array('http://purl.org/atom/ns#', $xml->getDocNamespaces(), true)
		) {
			throw new FeedParsingException('Failed to process Atom feed: ' . $e->getMessage());
		}

		// generate 'url' & 'timestamp' tags
		foreach ($xml->entry as $entry) {
			$entry->url = (string) $entry->link['href'];
			$entry->timestamp = strtotime($entry->updated);
		}
		$feed = new self;
		$feed->xml = $xml;
		return $feed;
	}


	/**
	 * Returns property value. Do not call directly.
	 * @param  string  tag name
	 * @return SimpleXMLElement
	 */
	public function __get($name)
	{
		return $this->xml->{$name};
	}


	/**
	 * Sets value of a property. Do not call directly.
	 * @param  string  property name
	 * @param  mixed   property value
	 * @return void
	 */
	public function __set($name, $value)
	{
		throw new Exception("Cannot assign to a read-only property '$name'.");
	}


	/**
	 * Converts a SimpleXMLElement into an array.
	 * @param  SimpleXMLElement
	 * @return array
	 */
	public function toArray(SimpleXMLElement $xml = null)
	{
		if ($xml === null) {
			$xml = $this->xml;
		}

		if (!$xml->children()) {
			return (string) $xml;
		}

		$arr = [];
		foreach ($xml->children() as $tag => $child) {
			if (count($xml->$tag) === 1) {
				$arr[$tag] = $this->toArray($child);
			} else {
				$arr[$tag][] = $this->toArray($child);
			}
		}

		return $arr;
	}

    /**
     * Loads XML from cache or performs an HTTP request to retrieve the feed.
     * Implements a caching mechanism and tries various methods for making HTTP requests.
     * @param string $url The URL to request
     * @param string|null $user Optional username for HTTP authentication
     * @param string|null $pass Optional password for HTTP authentication
     * @return SimpleXMLElement A SimpleXMLElement object with the feed data
     * @throws FeedException if the feed cannot be loaded
     */
	private static function loadXml($url, $user, $pass)
	{
		$e = self::$cacheExpire;
		$cacheFile = self::$cacheDir . '/feed.' . md5(serialize(func_get_args())) . '.xml';

		try {
			if (self::$cacheDir && $data = @file_get_contents($cacheFile) && (time() - @filemtime($cacheFile) <= (is_string($e) ? strtotime($e) - time() : $e))) {
				// Cache is valid
			} elseif ($data = trim(self::httpRequest($url, $user, $pass))) {
				if (self::$cacheDir) {
					if (!@file_put_contents($cacheFile, $data)) {
						throw new FeedConnectionException('Cannot load feed data: ' . $ex->getMessage());
					}
				}
			} else {
				throw new FeedConnectionException('Failed to load feed data.');
			}
		} catch (Exception $ex) {
			// This catches any unexpected exceptions and rethrows them as a FeedException.
			throw new FeedException('An unexpected error occurred: ' . $ex->getMessage());
		}

		try {
			$xml = new SimpleXMLElement($data, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NOCDATA);
		} catch (Exception $ex) {
			throw new FeedParsingException('Failed to parse feed data: ' . $ex->getMessage());
		}

		return $xml;
	}

    /**
     * Processes an HTTP request using the best available method.
     * Tries to use GuzzleHttp first, falls back to cURL, and finally uses file_get_contents.
     * @param string $url The URL to request
     * @param string|null $user Optional username for HTTP authentication
     * @param string|null $pass Optional password for HTTP authentication
     * @return string|false The response body as a string on success, or false on failure
     * @throws FeedException if all methods to make an HTTP request fail
     */
	private static function httpRequest($url, $user, $pass)
	{
		// Versuche, GuzzleHttp zu verwenden
		if (class_exists('GuzzleHttp\Client')) {
			try {
				$client = new \GuzzleHttp\Client(['timeout' => 20, 'verify' => false]);
				$options = [
					'headers' => [
						'User-Agent' => self::$userAgent,
					],
				];
				if ($user !== null && $pass !== null) {
					$options['auth'] = [$user, $pass];
				}
				$response = $client->request('GET', $url, $options);
				return (string) $response->getBody();
			} catch (\Exception $e) {
				// Spezifische Exception für GuzzleHttp Fehler
				throw new FeedConnectionException('GuzzleHttp failed: ' . $e->getMessage());
			}
		}

		// Fallback auf cURL
		if (extension_loaded('curl')) {
			$curl = curl_init();
			curl_setopt($curl, CURLOPT_URL, $url);
			if ($user !== null || $pass !== null) {
				curl_setopt($curl, CURLOPT_USERPWD, "$user:$pass");
			}
			curl_setopt($curl, CURLOPT_USERAGENT, self::$userAgent);
			curl_setopt($curl, CURLOPT_HEADER, false);
			curl_setopt($curl, CURLOPT_TIMEOUT, 20);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			if (!ini_get('open_basedir')) {
				curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
			}
			$result = curl_exec($curl);
			if (curl_errno($curl) !== 0 || curl_getinfo($curl, CURLINFO_HTTP_CODE) !== 200) {
				// Spezifische Exception für cURL Fehler
				throw new FeedConnectionException('cURL error: ' . curl_error($curl));
			}
			return $result;
		}

		// Fallback auf file_get_contents
		$contextOptions = [
			'http' => [
				'method' => 'GET',
				'header' => "User-Agent: " . self::$userAgent . "\r\n",
			],
		];
		if ($user !== null && $pass !== null) {
			$contextOptions['http']['header'] .= 'Authorization: Basic ' . base64_encode("$user:$pass") . "\r\n";
		}
		$context = stream_context_create($contextOptions);
		$result = file_get_contents($url, false, $context);
		if ($result === false) {
			// Spezifische Exception für file_get_contents Fehler
			throw new FeedConnectionException('file_get_contents failed');
		}
		return $result;
	}

    /**
     * Adjusts XML namespaces to make them more accessible.
     * @param SimpleXMLElement $el The XML element to adjust namespaces for
     * @return void
     */
	private static function adjustNamespaces($el)
	{
		foreach ($el->getNamespaces(true) as $prefix => $ns) {
			if ($prefix === '') {
				continue;
			}
			$children = $el->children($ns);
			foreach ($children as $tag => $content) {
				$el->{$prefix . ':' . $tag} = $content;
			}
		}
	}
}

/**
 * An exception generated by Feed.
 * Represents errors that occur during feed processing.
 */
class FeedException extends Exception
{
}

class FeedConnectionException extends FeedException
{
	protected $message = 'Failed to connect to the feed URL.';
}

class FeedParsingException extends FeedException
{
	protected $message = 'Failed to parse the feed content.';
}

class FeedCacheException extends FeedException
{
	protected $message = 'Cache-related error occurred.';
}
