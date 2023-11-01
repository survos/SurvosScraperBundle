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
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Component\HttpClient\CachingHttpClient;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpKernel\HttpCache\Store;

// doesn't appear be be working :-(

class CacheScraperService
{
    private CachingHttpClient $cachingHttpClient;
    public function __construct(
        private string              $dir,
        private HttpClientInterface $httpClient,
        private ?LoggerInterface    $logger,

    )
    {
        $store = new Store($this->dir);
        $this->cachingHttpClient = new CachingHttpClient($this->httpClient, $store);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function scrape($url, array $parameters=[], string $method='GET'): ResponseInterface
    {
        // this won't hit the network if the resource is already in the cache
        return $this->cachingHttpClient->request($method, $url, $parameters);
    }

    public function scrapeData($url, array $parameters=[], bool $throw=true): ?array
    {
        try {
            return $this->scrape($url, $parameters)->toArray($throw);
        } catch (ClientExceptionInterface $e) {
        } catch (DecodingExceptionInterface $e) {
        } catch (RedirectionExceptionInterface $e) {
        } catch (ServerExceptionInterface $e) {
        } catch (TransportExceptionInterface $e) {
        } catch (\Exception $e) {
            return null;
        }
        return null;
    }

}

