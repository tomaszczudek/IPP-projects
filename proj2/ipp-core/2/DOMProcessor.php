<?php

namespace IPP\Student;

use DOMElement;

class DOMProcessor
{
    /**
     * Zpracuje celý programový XML uzel a vrátí jeho strukturu jako pole tříd.
     *
     * @param DOMElement $program Kořenový XML uzel <program>
     * @return array{
     *     classes: array<int, array{
     *         name: string,
     *         parent: string,
     *         methods: array<int, array{
     *             selector: string,
     *             block: array{
     *                 type: string,
     *                 arity: int,
     *                 parameters: array<int, array{name: string, order: int}>,
     *                 statements: array<int, array{variable: array{type: string, name: string}, expression: array}>
     *             }
     *         }>
     *     }>
     * }
     */
    public function process(DOMElement $program): array
    {
        return [
            'classes' => $this->parseClasses($program)
        ];
    }

    /**
     * Zpracuje všechny třídy v programu a vrátí jejich pole.
     *
     * @param DOMElement $program XML uzel <program>
     * @return array<int, array{
     *     name: string,
     *     parent: string,
     *     methods: array<int, array{
     *         selector: string,
     *         block: array{
     *             type: string,
     *             arity: int,
     *             parameters: array<int, array{name: string, order: int}>,
     *             statements: array<int, array{variable: array{type: string, name: string}, expression: array}>
     *         }
     *     }>
     * }>
     */
    private function parseClasses(DOMElement $program): array
    {
        $classes = [];
        foreach ($program->getElementsByTagName('class') as $classElement) {
            $classes[] = $this->parseClass($classElement);
        }
        return $classes;
    }

    /**
     * Zpracuje jednu třídu a vrátí její jméno, rodiče a metody.
     *
     * @param DOMElement $class XML uzel <class>
     * @return array{
     *     name: string,
     *     parent: string,
     *     methods: array<int, array{
     *         selector: string,
     *         block: array{
     *             type: string,
     *             arity: int,
     *             parameters: array<int, array{name: string, order: int}>,
     *             statements: array<int, array{variable: array{type: string, name: string}, expression: array}>
     *         }
     *     }>
     * }
     */
    private function parseClass(DOMElement $class): array
    {
        return [
            'name' => (string)$class->getAttribute('name'),
            'parent' => (string)$class->getAttribute('parent'),
            'methods' => $this->parseMethods($class)
        ];
    }


    /**
     * Zpracuje všechny metody třídy a vrátí je jako pole.
     *
     * @param DOMElement $class XML uzel <class>
     * @return array<int, array{
     *     selector: string,
     *     block: array{
     *         type: string,
     *         arity: int,
     *         parameters: array<int, array{name: string, order: int}>,
     *         statements: array<int, array{variable: array{type: string, name: string}, expression: array}>
     *     }
     * }>
     */
    private function parseMethods(DOMElement $class): array
    {
        $methods = [];
        foreach ($class->getElementsByTagName('method') as $method) {
            $blockNode = $method->getElementsByTagName('block')->item(0);
            $methods[] = [
                'selector' => $method->getAttribute('selector'),
                'block' => $this->parseBlock($blockNode)
            ];
        }
        return $methods;
    }


    /**
     * Zpracuje blok (tělo metody nebo lambda) a vrátí jeho strukturu.
     *
     * @param DOMElement $block XML uzel <block>
     * @return array{
     *     type: string,
     *     arity: int,
     *     parameters: array<int, array{name: string, order: int}>,
     *     statements: array<int, array{variable: array{type: string, name: string}, expression: array}>
     * }
     */
    private function parseBlock(DOMElement $block): array
    {
        if ((int)$block->getAttribute('arity') == 0) {
            return [
                'type' => 'block',
                'arity' => 0,
                'parameters' => [],
                'statements' => $this->parseStatements($block)
            ];
        }

        return [
            'type' => 'block',
            'arity' => (int)$block->getAttribute('arity'),
            'parameters' => $this->parseParameters($block),
            'statements' => $this->parseStatements($block)
        ];
    }

    /**
     * Zpracuje parametry bloku/metody a vrátí je jako pole.
     *
     * @param DOMElement $block XML uzel <block>
     * @return array<int, array{name: string, order: int}>
     */
    private function parseParameters(DOMElement $block): array
    {
        $params = [];
        foreach ($block->getElementsByTagName('parameter') as $param) {
            $params[] = [
                'name' => $param->getAttribute('name'),
                'order' => (int)$param->getAttribute('order') - 1
            ];
        }
        usort($params, fn($a, $b) => $a['order'] <=> $b['order']);

        return $params;
    }

    /**
     * Zpracuje všechny přiřazovací příkazy v bloku a vrátí je jako pole.
     *
     * @param DOMElement $block XML uzel <block>
     * @return array<int, array{variable: array{type: string, name: string}, expression: array}>
     */
    private function parseStatements(DOMElement $block): array
    {
        $assigns = [];

        foreach ($block->childNodes as $child) {
            if ($child instanceof DOMElement && $child->nodeName === 'assign') {
                $order = (int) $child->getAttribute('order');
                $assigns[] = ['order' => $order, 'node' => $child];
            }
        }

        usort($assigns, fn($a, $b) => $a['order'] <=> $b['order']);
        $statements = [];
        foreach ($assigns as $assign) {
            $statements[] = [
                'variable' => $this->parseVariable($assign['node']),
                'expression' => $this->parseExpression($assign['node'])
            ];
        }

        return $statements;
    }


    /**
     * Vrátí pole s informací o proměnné z přiřazení.
     *
     * @param DOMElement $assign XML uzel <assign>
     * @return array{type: string, name: string}
     */
    private function parseVariable(DOMElement $assign): array
    {
        $varElement = $assign->getElementsByTagName('var')->item(0);
        return [
            'type' => 'variable',
            'name' => $varElement->getAttribute('name')
        ];
    }

    /**
     * Zpracuje výraz z přiřazení a vrátí jeho AST reprezentaci.
     *
     * @param DOMElement $assign XML uzel <assign>
     * @return array<string, mixed>
     */
    private function parseExpression(DOMElement $assign): array
    {
        $exprElement = $assign->getElementsByTagName('expr')->item(0);
        return $this->parseElement($exprElement);
    }

    /**
     * Zpracuje libovolný XML uzel s výrazem a vrátí jeho AST reprezentaci.
     *
     * @param DOMElement $element XML uzel <expr> nebo jiný
     * @return array<string, mixed>
     */
    private function parseElement(DOMElement $element): array
    {
        $node = $element->firstChild;
        while ($node && !$node instanceof DOMElement) {
            $node = $node->nextSibling;
        }

        if (!$node) {
            return [];
        }

        return $this->parseNode($node);
    }

    /**
     * Zpracuje libovolný AST uzel podle jeho typu.
     *
     * @param DOMElement $element
     * @return array<string, mixed>
     */
    private function parseNode(DOMElement $element): array
    {
        return match ($element->nodeName) {
            'literal' => $this->parseLiteral($element),
            'var' => $this->parseVar($element),
            'send' => $this->parseSend($element),
            'block' => $this->parseBlock($element),
            default => throw new \RuntimeException("Unknown node type: {$element->nodeName}")
        };
    }

    /**
     * Zpracuje literál a vrátí jeho strukturu.
     *
     * @param DOMElement $literal
     * @return array{type: string, class: string, value: string}
     */
    private function parseLiteral(DOMElement $literal): array
    {
        return [
            'type' => 'literal',
            'class' => $literal->getAttribute('class'),
            'value' => $literal->getAttribute('value')
        ];
    }

    /**
     * Zpracuje proměnnou a vrátí její strukturu.
     *
     * @param DOMElement $var
     * @return array{type: string, name: string}
     */
    private function parseVar(DOMElement $var): array
    {
        return [
            'type' => 'variable',
            'name' => $var->getAttribute('name')
        ];
    }

    /**
     * Zpracuje message send a vrátí jeho strukturu.
     *
     * @param DOMElement $send
     * @return array{type: string, selector: string, receiver: array, arguments: array}
     */
    private function parseSend(DOMElement $send): array
    {
        $receiver = null;
        $args = [];
        foreach ($send->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }

            if ($child->nodeName === 'arg') {
                $argExpr = $child->getElementsByTagName('expr')->item(0);
                if ($argExpr !== null) {
                    $args[] = $this->parseElement($argExpr);
                }
            } elseif ($child->nodeName === 'expr' && $receiver === null) {
                $receiver = $this->parseElement($child);
            }
        }
        if ($receiver === null) {
            throw new \RuntimeException("Missing receiver in send message", 42);
        }

        return [
            'type' => 'message_send',
            'selector' => $send->getAttribute('selector'),
            'receiver' => $receiver,
            'arguments' => $args
        ];
    }
}
