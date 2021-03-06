<?php

namespace Muglug\Blog;

class MarkdownBlog
{
	/**
	 * @var ArticleRepository
	 * @psalm-readonly
	 */
	public $articles;

	public function __construct(string $path, ?GithubConfig $github_config = null) {
		$this->articles = new ArticleRepository($path, $github_config);
	}
}