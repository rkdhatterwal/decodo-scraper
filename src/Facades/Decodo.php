<?php

namespace Rkdhatterwal\DecodoScraper\Facades;

use Illuminate\Support\Facades\Facade;
use Rkdhatterwal\DecodoScraper\DecodoClient;

/**
 * @method static \Rkdhatterwal\DecodoScraper\DTOs\ScrapeResult    scrape(string $url, array $options = [])
 * @method static \Illuminate\Support\Collection                  scrapeMany(array $urls, array $options = [])
 * @method static \Rkdhatterwal\DecodoScraper\DTOs\ScrapeResult    scrapeWithJs(string $url, array $options = [])
 * @method static \Rkdhatterwal\DecodoScraper\DTOs\ScrapeResult    screenshot(string $url, array $options = [])
 * @method static \Rkdhatterwal\DecodoScraper\DTOs\ScrapeResult    scrapeFromGeo(string $url, string $geo, array $options = [])
 * @method static \Rkdhatterwal\DecodoScraper\DTOs\ScrapeResult    scrapeAsMarkdown(string $url, array $options = [])
 * @method static \Rkdhatterwal\DecodoScraper\DTOs\ScrapeResult    scrapeWithParser(string $target, string $url, array $options = [])
 * @method static \Illuminate\Support\Collection                  send(\Rkdhatterwal\DecodoScraper\PayloadBuilder $builder)
 *
 * @see \Rkdhatterwal\DecodoScraper\DecodoClient
 */
class Decodo extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DecodoClient::class;
    }
}
