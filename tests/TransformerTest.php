<?php

namespace Amp\Transformers\Tests;

use Amp\Transformers\Transformer;

class TransformerTest extends \PHPUnit_Framework_TestCase
{
    /** @var Transformer */
    private $transformer;

    public function testTransformNameToApp()
    {
        $data = ['postid' => 5];
        $this->transformer->define('id', 'postid');

        $data = $this->transformer->forApp($data);
        $this->assertSame(['id' => 5], $data);
    }

    public function testTransformNameToStorage()
    {
        $data = ['id' => 5];
        $this->transformer->define('id', 'postid');

        $data = $this->transformer->forStorage($data);
        $this->assertSame(['postid' => 5], $data);
    }

    public function testTransformValueToApp()
    {
        $data = ['postid' => '5'];
        $this->transformer->define('id', 'postid', 'intval');

        $data = $this->transformer->forApp($data);
        $this->assertSame(['id' => 5], $data);
    }

    public function testTransformValueToStorage()
    {
        $data = ['id' => 5];
        $this->transformer->define('id', 'postid', null, 'strval');

        $data = $this->transformer->forStorage($data);
        $this->assertSame(['postid' => '5'], $data);
    }

    protected function setUp()
    {
        $this->transformer = new Transformer();
    }
}
