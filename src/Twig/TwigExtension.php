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


    private function fetch(string $url, array $paramters=[], string $method='GET', bool $cache=true): ?string {
        return $this->scraper->fetchUrlUsingCache($url, $paramters, method: $method, asData: false)['content']??null;
    }
    private function fetchData(string $url, array $paramters=[], string $method='GET'): array {
        return $this->scraper->fetchUrlUsingCache($url, $paramters, method: $method, asData: true)['data']??[];
    }

}
