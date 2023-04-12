<?php

declare(strict_types=1);

namespace Survos\Scraper\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\PdoAdapter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ScraperService {
    public function __construct(
        private string $dir,
                                private HttpClientInterface $httpClient,
                                private CacheInterface $cache,
                                private LoggerInterface $logger,
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

    public function fetchUrl(string $url, array $parameters = [], array $headers=[], string $key=null)
    {
        if (empty($key)) {
            $key = pathinfo($url);
        }
        {
            if (!file_exists($this->dir . $$key)) {
                $content = $this->httpClient->request('GET', $url, [
                    'query' => $parameters,
                    'timeout' => 10
                ])->getContent();
                file_put_contents($this->dir . $key, $content);
            }
            return $content;

        }

        $value = $this->cache->get( md5($url . json_encode($parameters)), function (ItemInterface $item) use ($url, $parameters) {
            try {
                $this->logger->warning("Fetching " . $url);
                $content = $this->httpClient->request('GET', $url, [
                    'query' => $parameters,
                    'timeout' => 10
                ])->getContent();
            } catch (\Exception $exception) {
                // eventually this will be in a message handler, so will automatically retry
                $this->logger->error($exception->getMessage());
                return null;
            }
            return $content;
        });
        return $value;

        $filename = $this->dataDir = $this->bag->get('app_data_dir') . sprintf('Larco%d.html', $id);
        return file_get_contents($filename);
    }


}

