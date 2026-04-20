<?php

namespace IPP\Student;

use DOMDocument;

/**
 * Třída pro převod DOMDocument s programem SOL25 do interní datové struktury.
 */
class XMLParser
{
    private DOMDocument $dom;

    public function __construct()
    {
        $this->dom = new DOMDocument();
    }

    /**
     * Parsuje XML dokument a vrací pole se strukturou programu.
     *
     * @param DOMDocument $xmlString XML dokument s kořenem <program>
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
    public function parse(DOMDocument $xmlString): array
    {
        // Uloží DOM dokument do vlastnosti třídy
        $this->dom = $xmlString;
        // Vytvoří instanci DOMProcessoru pro další zpracování
        $processor = new DOMProcessor();
        // Vrací strukturu programu - pole tříd
        return $processor->process($this->dom->documentElement);
    }
}
