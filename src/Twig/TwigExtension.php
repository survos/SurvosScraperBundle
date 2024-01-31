<?php

namespace Survos\Scraper\Twig;

use Survos\Scraper\Service\CacheScraperService;
use Survos\Scraper\Service\ScraperService;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigExtension extends AbstractExtension
{
    public function __construct(private readonly ScraperService $scraper)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('request', $this->fetch(...)),
            new TwigFunction('request_data', $this->fetchData(...))
        ];
    }


    /**
     * @param array<string, mixed> $parameters
     */
    private function fetch(string $url, array $parameters=[], string $method='GET', bool $cache=true): ?string {
        return $this->scraper->fetchUrlUsingCache($url, $parameters, method: $method, asData: null)['content']??null;
    }
    /**
     * @param array<string, mixed> $parameters
     * @return array
     */
    private function fetchData(string $url, array $parameters=[], string $method='GET', ?string $asData='array'): array|object|null {
        return $this->scraper->fetchData($url, $parameters, method: $method, asData: $asData);
    }

}
