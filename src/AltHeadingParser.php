<?php

namespace Muglug\Blog;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Node\Inline\Text;

class AltHeadingParser
{
    public function __invoke(DocumentParsedEvent $event): void
    {
        foreach ($event->getDocument()->iterator() as $node) {
            if (!$node instanceof Heading) {
                continue;
            }

            $text = '';
            $lastTextNode = null;
            foreach ($node->children() as $child) {
                if ($child instanceof Text) {
                    $text .= $child->getLiteral();
                    $lastTextNode = $child;
                }
            }

            $id = preg_replace('/[^a-z0-9\-]+/', '', strtolower(str_replace(' ', '-', $text)));
            $node->data->set('attributes/id', $id);

            if ($lastTextNode !== null) {
                $lastTextNode->setLiteral(self::preventOrphans($lastTextNode->getLiteral()));
            }
        }
    }

    public static function preventOrphans(string $text): string
    {
        $article_title_parts = explode(' ', $text);

        if (count($article_title_parts) > 1) {
            $last_word = array_pop($article_title_parts);
            return implode(' ', $article_title_parts) . "\xC2\xA0" . $last_word;
        }

        return $text;
    }
}
