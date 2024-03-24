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
     * @return Feed Returns an instance of the Feed class.
     * @throws FeedException Throws FeedException if feed cannot be loaded or parsed.
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
	 * @param  string  RSS feed URL
	 * @param  string  optional user name
	 * @param  string  optional password
	 * @return Feed
	 * @throws FeedException
	 */
	public static function loadRss($url, $user = null, $pass = null)
	{
		return self::fromRss(self::loadXml($url, $user, $pass));
	}


	/**
	 * Loads Atom feed.
	 * @param  string  Atom feed URL
	 * @param  string  optional user name
	 * @param  string  optional password
	 * @return Feed
	 * @throws FeedException
	 */
	public static function loadAtom($url, $user = null, $pass = null)
	{
		return self::fromAtom(self::loadXml($url, $user, $pass));
	}


	private static function fromRss(SimpleXMLElement $xml)
	{
		if (!$xml->channel) {
			throw new FeedException('Invalid feed.');
		}

		self::adjustNamespaces($xml);

		foreach ($xml->channel->item as $item) {
			// converts namespaces to dotted tags
			self::adjustNamespaces($item);

			// generate 'url' & 'timestamp' tags
			$item->url = (string) $item->link;
			if (isset($item->{'dc:date'})) {
				$item->timestamp = strtotime($item->{'dc:date'});
			} elseif (isset($item->pubDate)) {
				$item->timestamp = strtotime($item->pubDate);
			}
		}
		$feed = new self;
		$feed->xml = $xml->channel;
		return $feed;
	}


	private static function fromAtom(SimpleXMLElement $xml)
	{
		if (
			!in_array('http://www.w3.org/2005/Atom', $xml->getDocNamespaces(), true)
			&& !in_array('http://purl.org/atom/ns#', $xml->getDocNamespaces(), true)
		) {
			throw new FeedException('Invalid feed.');
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
     * Implements a caching mechanism and tries different methods for making HTTP requests.
     * @param string $url The URL to request
     * @param string|null $user Optional username for HTTP authentication
     * @param string|null $pass Optional password for HTTP authentication
     * @return SimpleXMLElement Returns a SimpleXMLElement object with the feed data.
     * @throws FeedException Throws FeedException if the feed cannot be loaded.
     */
	private static function loadXml($url, $user, $pass)
	{
		$e = self::$cacheExpire;
		$cacheFile = self::$cacheDir . '/feed.' . md5(serialize(func_get_args())) . '.xml';

		if (
			self::$cacheDir
			&& (time() - @filemtime($cacheFile) <= (is_string($e) ? strtotime($e) - time() : $e))
			&& $data = @file_get_contents($cacheFile)
		) {
			// ok
		} elseif ($data = trim(self::httpRequest($url, $user, $pass))) {
			if (self::$cacheDir) {
				if (!@file_put_contents($cacheFile, $data)) {
					throw new FeedException('Error when writing to the cache.');
				} else {
					file_put_contents($cacheFile, $data);
				}
			}
		} elseif (self::$cacheDir && $data = @file_get_contents($cacheFile)) {
			// ok
		} else {
			throw new FeedException('Cannot load feed.');
		}

		return new SimpleXMLElement($data, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NOCDATA);
	}

    /**
     * Processes an HTTP request using the best available method.
     * Tries to use GuzzleHttp first, falls back to cURL, and finally uses file_get_contents.
     * @param string $url The URL to request
     * @param string|null $user Optional username for HTTP authentication
     * @param string|null $pass Optional password for HTTP authentication
     * @return string|false Returns the response body as a string on success, or false on failure.
     * @throws FeedException Throws FeedException if all methods to make an HTTP request fail.
     */
	private static function httpRequest($url, $user, $pass)
	{
		// Schritt 1: Versuche GuzzleHttp zu verwenden
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
				// Guzzle fehlgeschlagen, gehe zu cURL oder file_get_contents über
			}
		}

		// Schritt 2: Fallback auf cURL, wenn Guzzle nicht verfügbar oder fehlgeschlagen ist
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
			if (curl_errno($curl) === 0 && curl_getinfo($curl, CURLINFO_HTTP_CODE) === 200) {
				return $result;
			}
		}

		// Schritt 3: Fallback auf file_get_contents, wenn cURL nicht verfügbar ist
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

		return file_get_contents($url, false, $context);
	}

    /**
     * Adjusts XML namespaces to make them more accessible.
     * @param SimpleXMLElement $el The element to adjust namespaces for.
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
