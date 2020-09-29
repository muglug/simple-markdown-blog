<?php

namespace Muglug\Blog;

/** @psalm-immutable */
class GithubConfig
{
	public $owner;
	public $repo;
	public $token;

	public function __construct(string $owner, string $repo, string $token)
	{
		$this->owner = $owner;
		$this->repo = $repo;
		$this->token = $token;
	}
}