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
        private ?CacheInterface      $cache,
        private string $prefix,
        private string $sqliteFilename,
        LoggerInterface $logger = null,
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
    public function getCache(): CacheInterface
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

    public function fetchUrlFilename(string $url, array $parameters = [], array $headers = [], string $key = null): string
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
            $this->logger && $this->logger->info("Fetching $url to " . $fullPath);
            $content = $this->httpClient->request('GET', $url, [
                'query' => $parameters,
                'timeout' => 10
            ])->getContent();
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


    public function fetchUrlUsingCache(string $url, array $parameters = [], array $headers = [], string $key = null)
    {
        if (empty($key)) {
            $key = pathinfo($url, PATHINFO_FILENAME);
        }
        $sqliteFilename = $this->getFullFilename();
        if ( ($dir = pathinfo($sqliteFilename, PATHINFO_DIRNAME)) && !file_exists($dir)) {
            mkdir($dir, recursive: true);
        }
        $cache = new DoctrineDbalAdapter(
            'sqlite:///' . $sqliteFilename,
        );

//        dd($sqliteFilename, file_exists($sqliteFilename));
//        $cache = $this->cache;
        $slugger = new AsciiSlugger();
        $key = $slugger->slug($key)->toString();
//        assert($cache->hasItem($key), 'missing '. $key);

//        https://symfony.com/doc/current/components/cache/adapters/pdo_doctrine_dbal_adapter.html#using-doctrine-dbal
        $value = $cache->get( $key, function (ItemInterface $item) use ($url, $parameters, $headers) {
                $this->logger->warning("Fetching " . $url);
                $content = $this->httpClient->request('GET', $url, [
                    'query' => $parameters,
                    'timeout' => 10
                ])->getContent();
            try {
            } catch (\Exception $exception) {
                // eventually this will be in a message handler, so will automatically retry
                $this->logger->error($exception->getMessage());
                return null;
            }
            return $content;
        });
        return $value;

    }

    public function fetchUrl(string $url, array $parameters = [], array $headers = [], string $key = null)
    {
        // use the cache if it exists, otherwise, use the directory and prefix
        // maybe don't allow for images and other binary files?
        if ($this->cache) {
            return $this->fetchUrlUsingCache($url, $parameters, $headers, $key);

        } else {
            return file_get_contents($this->fetchUrlFilename($url, $parameters, $headers, $key));
        }
    }


}

