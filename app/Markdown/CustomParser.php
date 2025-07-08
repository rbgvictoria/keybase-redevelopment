<?php

namespace App\Markdown;

use Illuminate\Support\Arr;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\Mention\MentionExtension;
use League\CommonMark\Extension\SmartPunct\SmartPunctExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TableOfContents\TableOfContentsExtension;
use League\CommonMark\MarkdownConverter;
use TightenCo\Jigsaw\Parsers\MarkdownParserContract;

class CustomParser implements MarkdownParserContract
{
    private MarkdownConverter $converter;

    public function __construct()
    {

        $config = [
            'heading_permalink' => [
                'html_class' => 'heading-permalink',
                'id_prefix' => 'content',
                'apply_id_to_heading' => false,
                'heading_class' => '',
                'fragment_prefix' => 'content',
                'insert' => 'before',
                'min_heading_level' => 2,
                'max_heading_level' => 6,
                'title' => 'Permalink',
                'symbol' => '',
                'aria_hidden' => true,
            ],
            'table_of_contents' => [
                'html_class' => 'table-of-contents',
                'position' => 'placeholder',
                'style' => 'bullet',
                'min_heading_level' => 2,
                'max_heading_level' => 6,
                'normalize' => 'relative',
                'placeholder' => '__TOC__',
            ],
        ];
        
        $environment = new Environment($config);

        $environment->addExtension(new CommonMarkCoreExtension);

        collect(Arr::get($config, 'commonmark.extensions', [
            new AttributesExtension,
            new SmartPunctExtension,
            new StrikethroughExtension,
            new TableExtension,
            new HeadingPermalinkExtension,
            new TableOfContentsExtension,
        ]))->map(fn ($extension) => $environment->addExtension($extension));

        collect(
            Arr::get(app('config'), 'commonmark.renderers')
        )->map(fn ($renderer, $nodeClass) => $environment->addRenderer($nodeClass, $renderer));

        $this->converter = new MarkdownConverter($environment);
    }

    public function parse(string $text)
    {
        return $this->converter->convert($text);
    }
}
