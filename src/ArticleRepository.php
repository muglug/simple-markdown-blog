<?php

namespace Muglug\Blog;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

class ArticleRepository
{
    private $path;
    private $github_config;

    public function __construct(string $path, ?GithubConfig $github_config)
    {
        $this->path = $path;
        $this->github_config = $github_config;
    }

    /** @return Article[] */
    public function getAll() : array
    {
        $article_dir = $this->path;

        $articles = [];

        foreach (scandir($article_dir) as $file) {
            if (strpos($file, '.md') === (strlen($file) - 3)) {
                $name = substr($file, 0, -3);

                if (!self::isValidArticleName($name)) {
                    continue;
                }

                $article = self::get($name);

                if ($article) {
                    $date = new \DateTime($article->date, new \DateTimeZone('America/New_York'));

                    if ($date->format('U') < time()) {
                        $articles[] = $article;
                    }
                }
            }
        }

        usort(
            $articles,
            function (Article $a, Article $b) : int {
                return (\strtotime($a->date) < \strtotime($b->date)) ? 1 : -1;
            }
        );

        return $articles;
    }

    private static function isValidArticleName(string $name)
    {
        return preg_match('/^[a-z0-9\-]+$/', $name);
    }

    public function get(string $name) : Article
    {
        if (!self::isValidArticleName($name)) {
            throw new \UnexpectedValueException('Bad article name');
        }

        $is_preview = false;

        $markdown = $this->getMarkdown($name, $is_preview);

        // Extract metadata directly from the raw markdown frontmatter
        // (CommonMark v2 parses <!-- --> as HTML blocks, so inline parsers never see them)
        $metadata = self::extractFrontmatter($markdown);

        $notice = '';

        $html = self::convertMarkdownToHtml($markdown, new AltHtmlInlineParser(), $notice);

        $snippet = mb_substr(trim(strip_tags($html)), 0, 150);

        $description = substr($snippet, 0, strrpos($snippet, ' ')) . '…';

        if (isset($metadata['notice']) && $metadata['notice'] !== '') {
            $notice = self::convertMarkdownToHtml($metadata['notice'], null);
        }

        return new Article(
            $metadata['title'] ?? '',
            $description,
            $metadata['canonical'] ?? '',
            $metadata['date'] ?? '',
            $metadata['author'] ?? '',
            $name,
            $html,
            $notice,
            $is_preview
        );
    }

    public static function convertMarkdownToHtml(
        string $markdown,
        ?AltHtmlInlineParser $alt_html_inline_parser,
        string &$notice = ''
    ) : string {
        $alt_heading_parser = new AltHeadingParser();

        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new TableExtension());
        $environment->addEventListener(DocumentParsedEvent::class, $alt_heading_parser);

        if ($alt_html_inline_parser) {
            $environment->addInlineParser($alt_html_inline_parser, 100);
        }

        $converter = new MarkdownConverter($environment);

        return (string) $converter->convert($markdown);
    }

    /**
     * @return array<string, string>
     */
    private static function extractFrontmatter(string $markdown) : array
    {
        $metadata = [];

        if (preg_match('/^<!--\s*(.*?)\s*-->/s', $markdown, $matches)) {
            $lines = explode("\n", $matches[1]);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $colon_pos = strpos($line, ':');
                if ($colon_pos !== false) {
                    $key = trim(substr($line, 0, $colon_pos));
                    $value = trim(substr($line, $colon_pos + 1));
                    $metadata[$key] = $value;
                }
            }
        }

        return $metadata;
    }

    private function getMarkdown(string $name, bool &$is_preview) : string
    {
        if (!preg_match('/^[a-z0-9\-_]+$/', $name)) {
            throw new \UnexpectedValueException($name . ' is invalid');
        }
        $static_file_name = $this->path . '/' . $name . '.md';

        if (file_exists($static_file_name)) {
            return file_get_contents($static_file_name);
        }

        if (!$this->github_config) {
            throw new \UnexpectedValueException('Could not find ' . $name . ' and no GitHub config supplied for previews');
        }

        $markdown = self::getMarkdownFromGithub(
            $name,
            $this->github_config
        );
        $is_preview = true;
        return $markdown;
    }

    private static function getMarkdownFromGithub(string $name, GithubConfig $github_config) : string
    {
        $github_api_url = 'https://api.github.com';

        // Prepare new cURL resource
        $ch = curl_init(
            $github_api_url
                . '/repos/'
                . $github_config->owner
                . '/'
                . $github_config->repo
                . '/contents/'
                . $name . '.md'
        );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        // Set HTTP Header for POST request
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            [
                'Accept: application/vnd.github.v3.raw',
                'Authorization: token ' . $github_config->token,
                'User-Agent: Muglug Markdown Blog crawler',
            ]
        );

        // Submit the POST request
        $response = (string) curl_exec($ch);

        $status = curl_getinfo($ch, \CURLINFO_HTTP_CODE);

        // Close cURL session handle
        curl_close($ch);

        if (!$response || $status === 404) {
            throw new \UnexpectedValueException('Response should exist');
        }

        return $response;
    }
}
