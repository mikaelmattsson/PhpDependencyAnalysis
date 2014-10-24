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

namespace PhpDA\Parser\Visitor;

use PhpDA\Entity\Adt;
use PhpDA\Entity\AdtAwareInterface;
use PhpDA\Parser\Filter;
use PhpDA\Parser\Visitor\Feature;
use PhpDA\Plugin\ConfigurableInterface;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * @SuppressWarnings("PMD.CouplingBetweenObjects")
 */
abstract class AbstractVisitor extends NodeVisitorAbstract implements
    AdtAwareInterface,
    ConfigurableInterface
{
    /** @var Adt */
    private $adt;

    /** @var Filter\NodeNameInterface */
    private $nodeNameFilter;

    public function setOptions(array $options)
    {
        $this->getNodeNameFilter()->setOptions($options);
    }

    /**
     * @param Adt $adt
     */
    public function setAdt(Adt $adt)
    {
        $this->adt = $adt;
    }

    /**
     * @return Adt
     * @throws \DomainException
     */
    public function getAdt()
    {
        if (!$this->adt instanceof Adt) {
            throw new \DomainException('Adt has not been set');
        }

        return $this->adt;
    }

    /**
     * @param Filter\NodeNameInterface $nodeNameFilter
     */
    public function setNodeNameFilter(Filter\NodeNameInterface $nodeNameFilter)
    {
        $this->nodeNameFilter = $nodeNameFilter;
    }

    /**
     * @return Filter\NodeNameInterface
     */
    public function getNodeNameFilter()
    {
        if (!$this->nodeNameFilter instanceof Filter\NodeNameInterface) {
            $this->setNodeNameFilter(new Filter\NodeName);
        }

        return $this->nodeNameFilter;
    }

    /**
     * @param Node\Name $name
     * @param Node|null $node
     * @return void
     */
    private function exchange(Node\Name $name, Node $node = null)
    {
        if ($node) {
            $attributes = $node->getAttributes();
            foreach ($attributes as $attr => $value) {
                $name->setAttribute($attr, $value);
            }
        }
    }

    /**
     * @param Node\Name $name
     * @return null|Node\Name
     */
    private function filter(Node\Name $name)
    {
        return $this->getNodeNameFilter()->filter($name);
    }

    /**
     * @param Node\Name $name
     * @param Node|null $node
     * @return void
     */
    protected function collect(Node\Name $name, Node $node = null)
    {
        if (is_null($node)) {
            $node = clone $name;
        }

        if ($name = $this->filter($name)) {
            $this->exchange($name, $node);
            $this->modify($name);
            $adtMutator = $this->getAdtMutator();
            $this->getAdt()->$adtMutator($name);
        }
    }

    /**
     * @throws \RuntimeException
     * @return string
     */
    private function getAdtMutator()
    {
        if ($this->isDeclaredNamespaceCollector()) {
            return 'setDeclaredNamespace';
        }

        if ($this->isUsedNamespaceCollector()) {
            return 'addUsedNamespace';
        }

        if ($this->isUnsupportedNamespaceCollector()) {
            return 'addUnsupportedStmt';
        }

        if ($this->isNamespacedStringCollector()) {
            return 'addNamespacedString';
        }

        throw new \RuntimeException('Visitor ' . get_class($this) . ' has wrong interface');
    }

    /**
     * @return bool
     */
    private function isDeclaredNamespaceCollector()
    {
        return $this instanceof Feature\DeclaredNamespaceCollectorInterface;
    }

    /**
     * @return bool
     */
    private function isUsedNamespaceCollector()
    {
        return $this instanceof Feature\UsedNamespaceCollectorInterface;
    }

    /**
     * @return bool
     */
    private function isUnsupportedNamespaceCollector()
    {
        return $this instanceof Feature\UnsupportedNamespaceCollectorInterface;
    }

    /**
     * @return bool
     */
    private function isNamespacedStringCollector()
    {
        return $this instanceof Feature\NamespacedStringCollectorInterface;
    }

    /**
     * @param Node\Name $name
     * @return void
     */
    private function modify(Node\Name $name)
    {
        if ($this->isUnsupportedNamespaceCollector()) {
            $prefix = '?';
        }

        if ($this->isNamespacedStringCollector()) {
            $prefix = '§';
        }

        if (isset($prefix)) {
            $name->prepend($prefix);
        }
    }
}
