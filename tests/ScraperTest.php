<?php

namespace Survos\Scraper\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Survos\Scraper\Service\ScraperService;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ScraperTest extends TestCase
{
    public function testScraper()
    {
        $dir = '../data/museum-digital';
        $scraperService = $this->createMockService($dir);

        $this->assertSame($dir, trim($scraperService->getDir(), '/'));

        $dir = '../data/museum-digital-cache';
        $this->assertSame($dir, trim($scraperService->setDir($dir)->getDir(), '/'));
    }

    public function testFetchUrl()
    {
        $dir = '../data/museum-digital-cache';
        $scraperService = $this->createMockService($dir);

        $response = $scraperService->fetchUrl('https://example.com/test');
        $this->assertSame(json_encode(
            [
                'title' => 'Example',
                'labels' => ['Test label'],
            ]
        ), $response);
    }

    /**
     * @param string $dir
     * @return ScraperService
     */
    private function createMockService(string $dir): ScraperService
    {
        $mockResponse = new MockResponse(json_encode([
            'title' => 'Example',
            'labels' => ['Test label'],
        ]));

        $scraperService = new ScraperService(
            $dir,
            $mockClient = new MockHttpClient($mockResponse),
            null, // $this->createMock(CacheInterface::class),
            $this->createMock(LoggerInterface::class),
            'prefix',
            'test.sqlite'
        );

        return $scraperService;
    }
}
