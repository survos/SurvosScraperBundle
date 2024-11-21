<?php

declare(strict_types=1);

namespace Survos\Scraper\Service;

use JetBrains\PhpStorm\ArrayShape;
use Psr\Log\LoggerInterface;

//use Survos\Scraper\Cache\Adapter\TextCacheAdapter;
use Symfony\Component\Cache\Adapter\DoctrineDbalAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ScraperService
{

    // @todo: consider a key/value datastore instead of a cache: https://gist.github.com/sbrl/c3bfbbbb3d1419332e9ece1bac8bb71c
    public function __construct(
        private string              $dir,
        private HttpClientInterface $httpClient,
        private ?CacheInterface     $cache,
        private ?LoggerInterface    $logger,
        private string              $prefix,
        private string              $sqliteFilename,
    )
    {
    }

    /**
     * @return string
     */
    public function getSqliteFilename(): string
    {
        return $this->sqliteFilename;
    }

    /**
     * @param string $sqliteFilename
     */
    public function setSqliteFilename(string $sqliteFilename): void
    {
        $this->sqliteFilename = $sqliteFilename;
    }

    /**
     * @return CacheInterface|DoctrineDbalAdapter
     */
    public function getCache(): CacheInterface|DoctrineDbalAdapter
    {
        return $this->cache;
    }

    /**
     * @param CacheInterface $cache
     * @return ScraperService
     */
    public function setCache(?CacheInterface $cache): ScraperService
    {
        $this->cache = $cache;
        return $this;
    }

    /**
     * @return string
     */
    public function getDir(): string
    {
        return rtrim($this->dir, '/') . '/';
    }

    /**
     * @param string $dir
     * @return ScraperService
     */
    public function setDir(string $dir): ScraperService
    {
        $this->dir = $dir;
        return $this;
    }

    /**
     * @param string $prefix
     * @return ScraperService
     */
    public function setPrefix(string $prefix): ScraperService
    {
        $this->prefix = $prefix;
        return $this;
    }

    public function fetchUrlFilename(
        string $url,
        array  $parameters = [],
        array  $headers = [],
        string|null $key = null,
        string $method = 'GET'
    ): string
    {
        if (empty($key)) {
            $key = pathinfo($url, PATHINFO_FILENAME);
        }
        $fullPath = rtrim($this->dir, '/') . ($this->prefix ? '/' . rtrim($this->prefix . '/') : '') . '/' . $key;
        $cacheDir = pathinfo($fullPath, PATHINFO_DIRNAME);
        if (!file_exists($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        if (!file_exists($fullPath)) {
            $options = [
                'timeout' => 10
            ];
            if ($method == 'POST') {
                $options['json'] = $parameters;
            } else {
                $options['query'] = $parameters;
            }

            $options['headers'] = $headers;

            $this->logger && $this->logger->info("Fetching $url to " . $fullPath);
            $content = $this->httpClient->request($method, $url, $options)->getContent();
            file_put_contents($fullPath, $content);
        }
        return realpath($fullPath);
    }

    public function getFullFilename()
    {
        return $this->getDir() . $this->getPrefix() . $this->getSqliteFilename();
    }

    /**
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function fetchData(string $url, array $parameters = [], string $method = 'GET', ?string $asData = 'array'): array|object|null
    {
        if ($result = $this->fetchUrlUsingCache($url, $parameters, method: $method, asData: $asData)) {
            assert(is_array($result));
            assert(array_key_exists('data', $result));
            return $result['data'];
        } else {
            dd($result, $url);
            return $asData == 'array' ? [] : new \StdClass();
        }
    }


    public function fetchUrlUsingCache(
        string  $url,
        array   $parameters = [],
        array   $headers = [],
        string|null  $key = null,
        string  $method = 'GET',
        ?string $asData = null // or 'object', 'array'
    )
    {
        // if the cache is null, we might be inside of a test
        if (!$this->cache) {
            return $this->httpClient->request($method, $url, $parameters)->getContent();
        }

//        $request = $this->httpClient->request('GET', 'https://www.rappnews.com/search/?f=json&q=%22foothills+forum%22&s=start_time&sd=desc&t=article&nsa=eedition&app%5B0%5D=editorial');
//        $response = $request->getStatusCode();
//        dd($response, $request->getInfo());
//        $url = 'https://www.rappnews.com/search/';
//        $parameters = [
//            'f' => 'json',
//            's' => 'start_time',
//            'nsa' => 'eedition',
//            'q' => 'foothills forum',
//            't' => 'article',
//            'l' => 10
//        ];
//        dump($url, $parameters);
        if (empty($key)) {
            $key = pathinfo($url, PATHINFO_FILENAME); // sanity
            $key .= '-' . hash('xxh3', $url . json_encode($parameters));
        }

        assert($this->getCache());
        $cache = $this->getCache();
        if (!$cache) {
            $sqliteFilename = $this->getFullFilename();
            dd($sqliteFilename);
            if (($dir = pathinfo($sqliteFilename, PATHINFO_DIRNAME)) && !file_exists($dir)) {
                mkdir($dir, recursive: true);
            }
            $cache = new DoctrineDbalAdapter(
                'sqlite:///' . $sqliteFilename,
            );
            dd($cache::class);
        }

        $options = [
            'timeout' => 30
        ];
        if ($method == 'POST') {
            $options['json'] = $parameters;
        } else {
            $options['query'] = $parameters;
        }

        $options['headers'] = $headers;

//        dd(self::getFilename($cache));
//        dd($sqliteFilename, file_exists($sqliteFilename));
//        $cache = $this->cache;
        $slugger = new AsciiSlugger();
        $key = $slugger->slug($key)->toString();
//        assert($cache->hasItem($key), 'missing '. $key);
//        https://symfony.com/doc/current/components/cache/adapters/pdo_doctrine_dbal_adapter.html#using-doctrine-dbal

        // we need better logic for handling errors. For now, retry empty
//        $item = $cache->getItem($key);
//        if ($item->isHit()) {
//            $value = $item->get();
//            if (empty($value)) {
//                $cache->delete($key);
//            }
//        }
//        $cache->createTable(); // for debugging

        // return an array with status_code and optionally content or data (array)
        if ($this->httpClient instanceof MockHttpClient) {
            $responseData = $this->httpClient->request($method, $url, $options)->getContent();
        }
        dd($this->httpClient::class);
        $responseData = $cache->get($key, function (ItemInterface $item) use ($url, $options, $parameters, $key, $method) {

            $this->logger->info("Missing $key, Fetching " . $url);
            $options['query'] = $parameters;
            dd($this->httpClient::class);
            $response = $this->httpClient->request($method, $url, $options);
            $statusCode = $response->getStatusCode();
//            dd($url, $parameters, $response->getInfo('original_url'));

            try {
            } catch (\Exception $exception) {
                return null;
                // network error
            }

            $responseData['statusCode'] = $statusCode;
            $content = null;

            try {
                switch ($statusCode) {
                    case 200:
                        try {
                            $content = $response->getContent();
//                            if ($asData) {
//                                $content = $response->getContent();
//                                $responseData['data'] =
//                                    ($asData === 'array')
//                                        ? $response->toArray() // or json_decode true?
//                                        : json_decode($content, false);
//                            } else {
//                                $responseData['content'] = $response->getContent();
//                            }
                        } catch (\Exception $exception) {

                        }
                        break; // this could fail.
                    case 429:
                        dd($response, $responseData);
                        break;
                    case 403:
                    case 404:
                    default:
//                        $content = null;
//                        $content = json_encode(['statusCode' => $statusCode]);
                }
                $responseData['content'] = $content;
                $this->logger->info(sprintf("received " . $statusCode . ' storing to #%s', $key));
            } catch (\Exception $exception) {
                // eventually this will be in a message handler, so will automatically retry
                $this->logger->error($exception->getMessage());
            }
            return $responseData; // always an array where content is a STRING
        });

        $content = $responseData['content'] ?? null;
        // convert it AFTER it's left the cache.  Too messy if in the cache, at least for now.   Maybe someday.
        if ($asData && $content) {
            $content = json_decode($content, $asData === 'array');
        }
        if (!is_array($responseData)) {
//            dump($responseData);
        }
        if (is_array($responseData)) {
            $responseData['data'] = $content;
        }

        return $responseData;

    }

    public function prune($callback = null)
    {

    }

    public function clear()
    {
//        foreach self::g
    }

    static private function get_property(object $object, string $property)
    {
        $array = (array)$object;
        $propertyLength = strlen($property);
        foreach ($array as $key => $value) {
            $propertyNameParts = explode("\0", $key);
            $propertyName = end($propertyNameParts);
            if ($propertyName === $property) {
                return $value;
            }
        }
    }

    static public function getFilename(DoctrineDbalAdapter $cache)
    {
        $conn = self::get_property($cache, 'conn');
        $params = self::get_property($conn, 'params');
        return $params['path'];
    }

    private function getKeys(DoctrineDbalAdapter $cache)
    {
        static $pdo = [];
        $filename = self::getFilename($cache);
        if (!file_exists($filename) || !filesize($filename)) {
            return [];
        }
        try {
            $pdo = new \PDO('sqlite:///' . $filename);
            $stmt = $pdo->query('SELECT item_id from cache_items order by CAST(item_id AS INTEGER) DESC ');
            $pdo = null;
        } catch (\Exception $exception) {
            dd($filename, $exception->getMessage());
        }
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function purgeEmpty(DoctrineDbalAdapter $cache)
    {
//        dd($cache, self::getFilename($cache));
        foreach ($this->getKeys($cache) as $key) {
            $item = $cache->getItem($key);
            if (empty($item->get())) {
//                $item->expiresAt(now());
                $cache->delete($key);
            }
        }
    }


    public function fetchUrl(string  $url,
                             array $parameters = [],
                             array $headers = [],
                             string|null  $key = null,
                             string  $method = 'GET',
                             ?string $asData = 'object'
    )
    {
        // use the cache if it exists, otherwise, use the directory and prefix
        // maybe don't allow for images and other binary files?
        return $this->fetchUrlUsingCache($url, $parameters, $headers, $key, $method, $asData);

        if ($this->cache) {

        } else {
            assert((bool)$this->cache, 'no cache!');
            return file_get_contents($this->fetchUrlFilename($url, $parameters, $headers, $key, $method));
        }
    }

    public function request($url, array $parameters = [], string $method = 'GET'): ResponseInterface
    {
        // wrapper for http call.
        return $this->httpClient->request($method, $url, $parameters);
    }

}

