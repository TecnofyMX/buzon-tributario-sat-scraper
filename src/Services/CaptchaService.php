<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper\Services;

use PhpCfdi\ImageCaptchaResolver\CaptchaImage;
use PhpCfdi\ImageCaptchaResolver\CaptchaResolverInterface;
use Symfony\Component\DomCrawler\Crawler;
use Tecnofy\BuzonTributarioSatScraper\Exceptions\CaptchaSourceNotFoundException;
use Tecnofy\BuzonTributarioSatScraper\Internal\Page;

final class CaptchaService
{
    public function __construct(private CaptchaResolverInterface $captchaResolver)
    {
    }

    public function resolve(Page $page): string
    {
        $crawler = new Crawler($page->html, $page->uri);
        $images = $crawler->filter(
            '#divCaptcha img, .divCaptcha img, img[id*="captcha"], img[src^="data:image"]',
        );
        if (0 === $images->count()) {
            throw new CaptchaSourceNotFoundException('The captcha image was not found in the SAT login page.');
        }

        $source = $images->first()->attr('src');
        if (null === $source || ! str_starts_with($source, 'data:image')) {
            throw new CaptchaSourceNotFoundException('The SAT captcha is not an inline image.');
        }

        return $this->captchaResolver->resolve(CaptchaImage::newFromInlineHtml($source))->getValue();
    }
}
