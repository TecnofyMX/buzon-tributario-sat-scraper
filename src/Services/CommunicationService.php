<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper\Services;

use GuzzleHttp\RequestOptions;
use Tecnofy\BuzonTributarioSatScraper\CommunicationCollection;
use Tecnofy\BuzonTributarioSatScraper\Exceptions\UnexpectedPageException;
use Tecnofy\BuzonTributarioSatScraper\Internal\HttpRequester;
use Tecnofy\BuzonTributarioSatScraper\Internal\Page;
use Tecnofy\BuzonTributarioSatScraper\Url;

final class CommunicationService
{
    public function __construct(
        private HttpRequester $requester,
        private CommunicationParser $parser,
    ) {
    }

    public function collectUnread(Page $authenticatedPage): CommunicationCollection
    {
        $page = $this->parser->recognizesCommunicationsPage($authenticatedPage)
            ? $authenticatedPage
            : $this->requester->request('GET', Url::COMMUNICATIONS, [
                RequestOptions::HEADERS => ['Referer' => $authenticatedPage->uri],
            ]);

        if (! $this->parser->recognizesCommunicationsPage($page)) {
            throw new UnexpectedPageException('The SAT response does not contain Mis comunicados.');
        }

        return new CommunicationCollection(...$this->parser->parseUnread($page));
    }
}
