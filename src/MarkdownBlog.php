<?php

namespace Muglug\Blog;

class MarkdownBlog
{
	/**
	 * @var ArticleRepository
	 * @psalm-readonly
	 */
	public $articles;

	public function __construct(string $path) {
		$this->articles = new ArticleRepository($path);
	}
}