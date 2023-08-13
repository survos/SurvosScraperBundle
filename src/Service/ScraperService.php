<?php

declare(strict_types=1);

namespace Survos\Scraper\Service;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Cache\Adapter\DoctrineDbalAdapter;
use Symfony\Component\Cache\Adapter\PdoAdapter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ScraperService
{
    use LoggerAwareTrait;

    public function __construct(
        private string              $dir,
        private HttpClientInterface $httpClient,
        private ?CacheInterface     $cache,
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
     * @return CacheInterface
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
     * @param string $dir
     * @return ScraperService
     */
    public function setPrefix(string $prefix): ScraperService
    {
        $this->prefix = $prefix;
        return $this;
    }

    public function fetchUrlFilename(
        string $url,
        array $parameters = [],
        array $headers = [],
        string $key = null,
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


    public function fetchUrlUsingCache(
        string $url,
        array $parameters = [],
        array $headers = [],
        string $key = null,
        string $method = 'GET'
    )
    {
        if (empty($key)) {
            $key = pathinfo($url, PATHINFO_FILENAME);
            if ($method == 'POST') {
                $key .= '-' . hash('xxh3', json_encode($parameters));
            }
        }
        assert($this->getCache());
        if (!$cache = $this->getCache()) {
            $sqliteFilename = $this->getFullFilename();
            dd($sqliteFilename);
            if ( ($dir = pathinfo($sqliteFilename, PATHINFO_DIRNAME)) && !file_exists($dir)) {
                mkdir($dir, recursive: true);
            }
            $cache = new DoctrineDbalAdapter(
                'sqlite:///' . $sqliteFilename,
            );
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
        $item = $cache->getItem($key);
        if ($item->isHit()) {
            $value = $item->get();
            if (empty($value)) {
                $cache->delete($key);
            }
        }

        $value = $cache->get( $key, function (ItemInterface $item) use ($url, $options, $key, $method) {

            try {
                $this->logger->info("Fetching " . $url);
                $request = $this->httpClient->request($method, $url, $options);
                switch ($statusCode = $request->getStatusCode()) {
                    case 200: $content = $request->getContent(); break;
                    case 403:
                    case 404:
                    default: $content = null;
                }
                $this->logger->info(sprintf("received " . $statusCode. ' storing to #%s', $key));
            } catch (\Exception $exception) {
                // eventually this will be in a message handler, so will automatically retry
                $this->logger->error($exception->getMessage());
                return null;
            }
            return $content;
        });
        if (empty($value)) {
//            assert(false, $key . "\n" . self::getFilename($cache) );
        }
        return $value;

    }

    public function prune($callback=null)
    {

    }

    public function clear()
    {
//        foreach self::g
    }

    static private function get_property(object $object, string $property) {
        $array = (array) $object;
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
            $pdo = new \PDO('sqlite:///' . $filename );
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
            dd($item);
            if (empty($item->get())) {
                assert(false, $key);
                $item->expiresAt(now());
                $cache->delete($key);
            }
        }
    }


    public function fetchUrl(string $url, array $parameters = [], array $headers = [], string $key = null, string $method = 'GET')
    {
        // use the cache if it exists, otherwise, use the directory and prefix
        // maybe don't allow for images and other binary files?
        if ($this->cache) {
            return $this->fetchUrlUsingCache($url, $parameters, $headers, $key, $method);

        } else {
            return file_get_contents($this->fetchUrlFilename($url, $parameters, $headers, $key, $method));
        }
    }
}

