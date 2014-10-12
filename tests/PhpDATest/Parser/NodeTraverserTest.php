<?php
/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2014 Marco Muths
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace PhpDATest\Parser;

use PhpDA\Parser\NodeTraverser;

class NodeTraverserTest extends \PHPUnit_Framework_TestCase
{
    /** @var NodeTraverser */
    protected $fixture;

    /** @var \PhpDA\Plugin\LoaderInterface | \Mockery\MockInterface */
    protected $visitorLoader;

    /** @var \PhpParser\NodeVisitor | \Mockery\MockInterface */
    protected $visitor;

    protected function setUp()
    {
        $requiredVisitors = array(
            'PhpDA\Parser\Visitor\Required\MultiNamespaceDetector',
            'PhpParser\NodeVisitor\NameResolver',
            'PhpDA\Parser\Visitor\Required\DeclaredNamespaceCollector',
            'PhpDA\Parser\Visitor\Required\UsedNamespaceCollector',
        );
        $this->visitor = \Mockery::mock('PhpParser\NodeVisitor');
        $this->visitorLoader = \Mockery::mock('PhpDA\Plugin\LoaderInterface');
        foreach ($requiredVisitors as $fqn) {
            $this->visitorLoader->shouldReceive('get')->with($fqn, null)->andReturn($this->visitor);
        }

        $this->fixture = new NodeTraverser;
    }

    public function testMutateAndAccessVisitorLoader()
    {
        $this->fixture->setVisitorLoader($this->visitorLoader);
        $this->assertSame($this->visitorLoader, $this->fixture->getVisitorLoader());
    }

    public function testNullPointerExceptionForAccessVisitorLoader()
    {
        $this->setExpectedException('DomainException');
        $this->fixture->getVisitorLoader();
    }

    public function testBindingVisitors()
    {
        $visitors = array(
            'foo',
            '\\PhpDA\Parser\Visitor\Required\UsedNamespaceCollector\\',
            '\\bar\baz',
            'baz\baz\\',
            'PhpDA\Parser\Visitor\Required\MultiNamespaceDetector',
        );

        $this->visitorLoader->shouldReceive('get')->with('foo', null)->andReturn($this->visitor);
        $this->visitorLoader->shouldReceive('get')->with('bar\baz', null)->andReturn($this->visitor);
        $this->visitorLoader->shouldReceive('get')->with('baz\baz', null)->andReturn($this->visitor);
        $this->fixture->setVisitorLoader($this->visitorLoader);

        $this->fixture->bindVisitors($visitors);
    }

    public function testBindingVisitorsWithOptions()
    {
        $visitors = array(
            'foo',
            'bar\baz',
            'baz',
        );
        $options = array(
            'foo\\'       => array('foo'),
            '\\bar\baz\\' => 234,
        );

        $this->visitorLoader->shouldReceive('get')->with('foo', array('foo'))->andReturn($this->visitor);
        $this->visitorLoader->shouldReceive('get')->with('bar\baz', array(234))->andReturn($this->visitor);
        $this->visitorLoader->shouldReceive('get')->with('baz', null)->andReturn($this->visitor);
        $this->fixture->setVisitorLoader($this->visitorLoader);

        $this->fixture->bindVisitors($visitors, $options);
    }

    public function testBindingInvalidVisitor()
    {
        $this->setExpectedException('RuntimeException');

        $visitors = array('foo',);

        $this->visitorLoader->shouldReceive('get')->with('foo', null)->andReturn(false);
        $this->fixture->setVisitorLoader($this->visitorLoader);

        $this->fixture->bindVisitors($visitors);
    }

    public function testMutateAndAccessAnalysis()
    {
        $this->assertNull($this->fixture->getAnalysis());
        $analysis = \Mockery::mock('PhpDA\Entity\Analysis');
        $this->fixture->setAnalysis($analysis);
        $this->assertSame($analysis, $this->fixture->getAnalysis());
    }

    public function testTraversing()
    {
        $analysis = \Mockery::mock('PhpDA\Entity\Analysis');
        $this->fixture->setAnalysis($analysis);

        $visitors = array('foo');
        $visitor = \Mockery::mock('PhpDA\Parser\Visitor\AbstractVisitor');
        $visitor->shouldReceive('setAnalysis')->once()->with($analysis);
        $this->visitorLoader->shouldReceive('get')->with('foo', null)->andReturn($visitor);
        $this->fixture->setVisitorLoader($this->visitorLoader);
        $this->fixture->bindVisitors($visitors);

        $this->visitor->shouldIgnoreMissing();
        $visitor->shouldIgnoreMissing();

        $nodes = array('foo', 'bar');

        $this->assertSame($nodes, $this->fixture->traverse($nodes));
    }
}