<?php

namespace Torchlight\Commonmark\Tests;

use Illuminate\Support\Facades\Http;
use Torchlight\Block;
use Torchlight\Client;
use Torchlight\Commonmark\TorchlightExtension;
use League\CommonMark\DocParser;
use League\CommonMark\Environment;
use League\CommonMark\HtmlRenderer;
use Orchestra\Testbench\TestCase;

class CodeRendererTest extends TestCase
{

    protected function getEnvironmentSetUp($app)
    {
        config()->set('torchlight.token', 'token');

        $ids = [
            'block_id_1',
            'block_id_2',
            'block_id_3',
        ];

        Block::$generateIdsUsing = function () use (&$ids) {
            return array_shift($ids);
        };
    }

    protected function render($markdown)
    {
        $environment = Environment::createCommonMarkEnvironment();
        $environment->addExtension(new TorchlightExtension);

        $parser = new DocParser($environment);
        $htmlRenderer = new HtmlRenderer($environment);

        $document = $parser->parse($markdown);

        return $htmlRenderer->renderBlock($document);
    }

    /** @test */
    public function it_highlights_code_blocks()
    {
        $markdown = <<<'EOT'
before

```html
<div>html</div>
```
after
EOT;

        $response = [
            "blocks" => [[
                "id" => "block_id_1",
                "wrapped" => "<pre><code>highlighted</code></pre>",
            ]]
        ];

        Http::fake([
            'api.torchlight.dev/*' => Http::response($response, 200),
        ]);

        $html = $this->render($markdown);

        $expected = <<<EOT
<p>before</p>
<pre><code>highlighted</code></pre>
<p>after</p>

EOT;

        $this->assertEquals($expected, $html);
    }

    /** @test */
    public function gets_language_and_contents()
    {
        $markdown = <<<'EOT'
before

```foobarlang
<div>test</div>
```
after
EOT;

        Http::fake();

        $this->render($markdown);

        Http::assertSent(function ($request) {
            return $request['blocks'][0]['language'] === "foobarlang"
                && $request['blocks'][0]['code'] === "<div>test</div>";

        });
    }

    /** @test */
    public function it_sends_one_request_only_and_matches_by_id()
    {
        $markdown = <<<'EOT'
before

```php
some php
```

```ruby
some ruby
```

```js
some js
```
after
EOT;

        $response = [
            "blocks" => [[
                "id" => "block_id_3",
                "wrapped" => "some js",
            ], [
                "id" => "block_id_1",
                "wrapped" => "some php",
            ], [
                "id" => "block_id_2",
                "wrapped" => "some ruby",
            ]]
        ];

        Http::fake([
            'api.torchlight.dev/*' => Http::response($response, 200),
        ]);


        $html = $this->render($markdown);

        Http::assertSentCount(1);

        $expected = <<<EOT
<p>before</p>
some php
some ruby
some js
<p>after</p>

EOT;

        $this->assertEquals($expected, $html);
    }


}