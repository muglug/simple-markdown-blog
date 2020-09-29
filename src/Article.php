<?php

namespace Muglug\Blog;

/** @psalm-immutable */
class Article
{
	public $title;
	public $description;
	public $canonical;
	public $html;
	public $date;
	public $author;
	public $slug;
	public $notice;
	public $is_preview;

	public function __construct(
		string $title,
		string $description,
		string $canonical,
		string $date,
		string $author,
		string $slug,
		string $html,
		string $notice,
		bool $is_preview
	) {
		$this->title = $title;
		$this->description = $description;
		$this->canonical = $canonical;
		$this->html = $html;
		$this->date = $date;
		$this->author = $author;
		$this->slug = $slug;
		$this->is_preview = $is_preview;
		$this->notice = $notice;
	}

	public function getReadingMinutes() : int
	{
		$word_count = str_word_count(
			strip_tags(
				preg_replace('/<pre>(.*?)<\\/pre>/', '', $this->html)
			)
		);

		$word_count += 2 * substr_count($this->html, '<p>');

		$word_count += substr_count($this->html, '<h');

		$word_count += substr_count($this->html, '<code');

		$word_count += substr_count($this->html, '<a href=');

		return round(0.25 + ($word_count / 265));
	}
}