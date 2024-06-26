<?php

namespace Inilim\SpiderURL;

use DiDom\Document;
use DiDom\Element;

class SpiderURL
{
    // //domain.ru
    protected string $host_multi_http_not_www;
    // //www.domain.ru
    protected string $host_multi_http_www;

    // http://domain.ru
    protected string $host_http_not_www;
    // https://domain.ru
    protected string $host_https_not_www;

    // http://www.domain.ru
    protected string $host_http_www;
    // https://www.domain.ru
    protected string $host_https_www;

    // www.domain.ru
    protected string $host_www;
    // domain.ru
    protected string $host_not_www;

    /**
     * https?://domain.ru
     */
    protected string $start_url;
    // domain.ru
    protected string $host;
    /**
     * оставлять get параметры, все что идет после ?
     */
    protected bool $save_query;

    protected const CON = '://';
    protected ?Document $doc = null;

    public function __construct(
        string $start_url,
        bool $save_query = true
    ) {
        $this->save_query = $save_query;

        $start_url = \trim($start_url, '/');
        $start_url = \parse_url($start_url);
        if (!isset($start_url['scheme']) || !isset($start_url['host'])) {
            throw new \Exception('Отсутствуют важные ключи parse_url "scheme" и "host"');
        }
        $this->host      = $start_url['host'];
        $this->start_url = $start_url['scheme'] . self::CON . $this->host;
        // ----------------------
        $this->init();
        // de([
        //    $this->host_multi_http_www,
        //    $this->host_multi_http_not_www,
        //    $this->host_http_www,
        //    $this->host_http_not_www,
        //    $this->host_https_www,
        //    $this->host_https_not_www,
        //    $this->host_www,
        //    $this->host_not_www,
        // ]);
    }

    public function getDocObjFromHTML(string $html): Document
    {
        return new Document($html);
    }

    public function setHTML(string $html): void
    {
        $this->doc = $this->getDocObjFromHTML($html);
    }

    public function setDocObj(Document $doc): void
    {
        $this->doc = $doc;
    }

    public function getURLs(): array
    {
        if ($this->doc === null) return [];
        return $this->handleHrefs(
            $this->getAllHref()
        );
    }

    // public function getUnknownURLs(): array
    // {
    //     if ($this->doc === null) return [];
    //     return $this->handleHrefs(
    //         $this->getAllHref(),
    //         true,
    //     );
    // }

    // ------------------------------------------------------------------
    // ___
    // ------------------------------------------------------------------

    protected function init(): void
    {
        $host_www     = 'www.' . $this->removeWWW($this->host);
        $host_not_www = $this->removeWWW($this->host);
        // ----------------------
        $this->host_multi_http_www     = '//' . $host_www;
        $this->host_multi_http_not_www = '//' . $host_not_www;

        $this->host_http_www     = 'http' . self::CON . $host_www;
        $this->host_http_not_www = 'http' . self::CON . $host_not_www;

        $this->host_https_www     = 'https' . self::CON . $host_www;
        $this->host_https_not_www = 'https' . self::CON . $host_not_www;

        $this->host_www     = $host_www;
        $this->host_not_www = $host_not_www;
    }


    protected function removeWWW(string $url): string
    {
        $res = \preg_replace('#^(www\.)#i', '', $url);
        if (!\is_string($res)) return $url;
        $res = \preg_replace('#(\:\/\/www\.)#i', '://', $res);
        if (!\is_string($res)) return $url;
        return $res;
    }

    protected function getAllHref(): array
    {
        $tags_a = \_arr()->map(
            $this->doc->find('a'),
            function (Element $a) {
                return \trim($a->getAttribute('href') ?? '');
            }
        );
        return \array_filter($tags_a, fn (string $a) => $a !== '');
    }

    protected function filterURLs(array $hrefs, bool $unknown_url = false): array
    {
        return \array_filter($hrefs, function (string $href) use ($unknown_url) {
            $r = \_str()->startsWith($href, \array_merge($this->getAllVariants(), ['/']));

            if ($unknown_url) return !$r;
            return $r;
        });
    }

    protected function handleHrefs(array $hrefs, bool $unknown_url = false): array
    {
        if (!$hrefs) return [];

        $hrefs = $this->filterURLs($hrefs, $unknown_url);

        // ------------------------------------------------------------------
        // ___
        // ------------------------------------------------------------------

        $hrefs = \_arr()->map($hrefs, function (string $href) use ($unknown_url) {
            // ------------------------------------------------------------------
            // ___
            // ------------------------------------------------------------------

            if (!$unknown_url) {
                $href = \str_replace($this->getAllVariants(), '', $href);
            }

            // ------------------------------------------------------------------
            // ___
            // ------------------------------------------------------------------

            // если false тогда убираем все что идет после "?..."
            if (!$this->save_query) {
                $href = \preg_replace('#\?.+#', '', $href);
            } else {
                $href = \urldecode($href);
            }
            // убираем якорь
            $href = \preg_replace('#\#.+#', '', $href);
            $href = \rtrim($href, '/');

            if ($href === '') $href = '/';

            /** @var string $href */

            // ------------------------------------------------------------------
            // ___
            // ------------------------------------------------------------------
            return $href;
        });

        // ------------------------------------------------------------------
        // ___
        // ------------------------------------------------------------------

        $hrefs = \array_values(\array_unique($hrefs));

        // ------------------------------------------------------------------
        // ___
        // ------------------------------------------------------------------

        $hrefs = \_arr()->map($hrefs, function (string $href) use ($unknown_url) {
            $arr = [];

            if ($this->save_query) {
                $arr['path_and_query']       = $href;
                $arr['crc32_path_and_query'] = \crc32($arr['path_and_query'] ?? '');
            }

            if (!$unknown_url) {
                $arr['url']            = $this->start_url . $href;
            }

            $arr = $arr + \parse_url($arr['url']);

            $arr['crc32_url']            = \crc32($arr['url'] ?? '');
            $arr['crc32_path']           = \crc32($arr['path'] ?? '');

            return $arr;
        });


        // ------------------------------------------------------------------
        // ___
        // ------------------------------------------------------------------

        return $hrefs;
    }

    protected function getAllVariants(): array
    {
        return [
            $this->host_http_www,
            $this->host_http_not_www,
            $this->host_https_www,
            $this->host_https_not_www,
            $this->host_www,
            $this->host_not_www,
            $this->host_multi_http_www,
            $this->host_multi_http_not_www,
        ];
    }
}
