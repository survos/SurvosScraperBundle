<?php

declare(strict_types=1);

namespace Survos\Scraper\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\PdoAdapter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ScraperService
{
    public function __construct(
        private string              $dir,
        private HttpClientInterface $httpClient,
        private CacheInterface      $cache,
        private LoggerInterface     $logger,
    )
    {
    }

    /**
     * @return string
     */
    public function getDir(): string
    {
        return $this->dir;
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

    public function fetchUrlFilename(string $url, array $parameters = [], array $headers = [], string $key = null): string
    {
        if (empty($key)) {
            $key = pathinfo($url, PATHINFO_FILENAME);
        }
        $fullPath = rtrim($this->dir, '/') . '/' . $key;

        $cacheDir = pathinfo($fullPath, PATHINFO_DIRNAME);
        if (!file_exists($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        if (!file_exists($fullPath)) {
            $content = $this->httpClient->request('GET', $url, [
                'query' => $parameters,
                'timeout' => 10
            ])->getContent();
            file_put_contents($fullPath, $content);
        }
        return realpath($fullPath);
    }

    public function fetchUrl(string $url, array $parameters = [], array $headers = [], string $key = null)
    {
        return file_get_contents($this->fetchUrlFilename($url, $parameters, $headers, $key));
    }



}
