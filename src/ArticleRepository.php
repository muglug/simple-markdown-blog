<?php

namespace Muglug\Blog;

use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Ext\Table\TableExtension;

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
                $article = self::get(substr($file, 0, -3));

                if ($article) {
                    $date = new \DateTime($article->date, new \DateTimeZone('America/New_York'));
                    
                    if ($date->format('U') < mktime()) {
                        $articles[] = $article;
                    }
                }
            }
        }

        usort(
            $articles,
            function (Article $a, Article $b) : int {
                return (int) ($a->date < $b->date);
            }
        );

        return $articles;
    }
    
    public function get(
        string $name
    ) : ?Article {
        if (!preg_match('/^[a-z0-9\-]+$/', $name)) {
            return null;
        }

        $is_preview = false;

        try {
            $markdown = $this->getMarkdown($name, $is_preview);
        } catch (\Exception $e) {
            header("HTTP/1.0 404 Not Found");
            return null;
        }
        
        $alt_html_inline_parser = new AltHtmlInlineParser();
        
        $notice = '';

        $html = self::convertMarkdownToHtml($markdown, $alt_html_inline_parser, $notice);

        $snippet = mb_substr(trim(strip_tags($html)), 0, 150);

        $description = substr($snippet, 0, strrpos($snippet, ' ')) . '…';
        
        $date = $alt_html_inline_parser->getDate();
        $title = $alt_html_inline_parser->getTitle();
        $canonical = $alt_html_inline_parser->getCanonical();
        $author = $alt_html_inline_parser->getAuthor();

        return new Article(
            $title,
            $description,
            $canonical,
            $date,
            $author,
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

        $environment = \League\CommonMark\Environment::createCommonMarkEnvironment();

        // Add this extension
        $environment->addExtension(new TableExtension());
        $environment->addBlockParser($alt_heading_parser, 100);
        
        if ($alt_html_inline_parser) {
            $environment->addInlineParser($alt_html_inline_parser, 100);
        }

        $converter = new CommonMarkConverter([], $environment);
        
        $html = $converter->convertToHtml($markdown);
        
        if ($alt_html_inline_parser) {
            $notice = $converter->convertToHtml($alt_html_inline_parser->getNotice());
        }
        
        return $html;
    }

    private function getMarkdown(string $name, bool &$is_preview) : string
    {
        $static_file_name = $this->path . '/' . $name . '.md';

        if (file_exists($static_file_name)) {
            return file_get_contents($static_file_name);
        }

        if (!$this->github_config) {
            throw new \UnexpectedValueException('No GitHub config supplied');
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
