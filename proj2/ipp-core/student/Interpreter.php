<?php

namespace IPP\Student;

use IPP\Core\AbstractInterpreter;
use IPP\Core\ReturnCode;

/**
 * Třída Interpreter
 *
 * Zajišťuje kompletní běh programu v jazyce SOL25:
 * - Parsuje XML vstup do interní struktury tříd a metod
 * - Kontroluje základní strukturu programu (třída Main, metoda run)
 * - Připravuje a mapuje metody a jejich parametry
 * - Vyhodnocuje výrazy, přiřazuje proměnné, volá metody a bloky
 * - Spravuje kontext běhu, lokální proměnné, vestavěné třídy a atributy
 * - Zajišťuje správné předávání zpráv mezi objekty a zpracování chyb
 */

class Interpreter extends AbstractInterpreter
{
    private array $classMap = [];
    private array $methodMap = [];
    private array $classAttributes = [];
    private array $builtinClasses = [
        'Object' => ['new', 'from', 'identicalTo', 'equalTo', 'asString', 'isNumber', 'isString', 'isBlock', 'isNil'],
        'Nil' => ['new', 'from', 'identicalTo', 'equalTo', 'asString', 'isNumber', 'isString', 'isBlock', 'isNil'],
        'True' => ['new', 'from', 'identicalTo', 'equalTo', 'asString', 'isNumber', 'isString', 'isBlock', 'isNil',
                   'not', 'and', 'or', 'ifTrueifFalse'],
        'False' => ['new', 'from', 'identicalTo', 'equalTo', 'asString', 'isNumber', 'isString', 'isBlock', 'isNil',
                   'not', 'and', 'or', 'ifTrueifFalse'],
        'Integer' => ['new', 'from', 'identicalTo', 'equalTo', 'asString', 'isNumber', 'isString', 'isBlock', 'isNil',
        'plus', 'minus', 'multiplyBy', 'divBy', 'timesRepeat'],
        'String' => ['new', 'from', 'identicalTo', 'equalTo', 'asString', 'isNumber', 'isString', 'isBlock', 'isNil',
        'read', 'print', 'asInteger', 'concatenateWith', 'startsWithendsBefore'],
        'Block' => ['new', 'from', 'identicalTo', 'equalTo', 'asString', 'isNumber', 'isString', 'isBlock', 'isNil',
        'whileTrue'],
    ];


    /**
     * Spustí interpretaci programu.
     *
     * @return int Návratový kód podle výsledku běhu
     */
    public function execute(): int
    {
        try {
            $parser = new XMLParser();
            $parsedData = $parser->parse($this->source->getDOMDocument());

            $this->processStruct($parsedData);
            $this->checkProgramStructure($this->classMap, $this->methodMap);
            $this->prepareMethods();

            $this->invokeMethod('Main', 'run', []);
            return ReturnCode::OK;
        } catch (\RuntimeException $e) {
            $this->stderr->writeString($e->getMessage());
            return $e->getCode();
        }
    }

    /**
     * Zkontroluje, že program obsahuje třídu Main a metodu run s aritou 0.
     *
     * @param array $classes Mapování tříd
     * @param array $methods Mapování metod
     * @return int Návratový kód (OK nebo chyba)
     */
    private function checkProgramStructure(array $classes, array $methods): int
    {
        if (!isset($classes['Main'])) {
            $this->stderr->writeString("Missing class Main");
            exit(ReturnCode::INVALID_SOURCE_STRUCTURE_ERROR);
        }

        if (!isset($methods['Main']['run'])) {
            $this->stderr->writeString("Missing method run in class Main");
            exit(ReturnCode::PARSE_MAIN_ERROR);
        }

        $runMethod = $methods['Main']['run'];
        if ($runMethod['arity'] !== 0) {
            $this->stderr->writeString("Method run in class Main must have arity 0");
            exit(ReturnCode::PARSE_MAIN_ERROR);
        }

        return ReturnCode::OK;
    }

    /**
     * Zpracuje pole tříd a metod do interních map (classMap, methodMap).
     *
     * @param array $parsedClasses Výstup z XML parseru
     */
    private function processStruct(array $parsedClasses): void
    {
        foreach ($parsedClasses['classes'] as $classInfo) {
            $name = $classInfo['name'];
            $methodNames = [];

            foreach ($classInfo['methods'] as $method) {
                $selector = $method['selector'];
                $arity = $method['block']['arity'];
                $methodNames[] = $selector;
                $this->methodMap[$name][$selector] = [
                    'name'  => $selector,
                    'type' => "block",
                    'arity' => $arity,
                    'parameters' => $method['block']['parameters'],
                    'statements' => $method['block']['statements']
                ];
            }

            $this->classMap[$name] = [
                'parent' => $classInfo['parent'],
                'methods' => $methodNames
            ];
        }
    }

    /**
     * Připraví metody (bloky) pro rychlý běh – převede AST příkazy na jednodušší strukturu.
     */
    private function prepareMethods(): void
    {
        foreach ($this->classMap as $className => $classInfo) {
            if (isset($this->methodMap[$className])) {
                foreach ($this->methodMap[$className] as $selector => &$methodData) {
                    $methodData['statements'] = $this->getVarExpr($methodData);
                }
            }
        }
    }

    /**
     * Převede pole AST příkazů na jednodušší pole dvojic proměnná-výraz.
     *
     * @param array $block AST blok metody
     * @return array Pole přiřazení (proměnná, výraz)
     */
    private function getVarExpr(array $block): array
    {
        $blockStatements = $block['statements'];

        $pairs = [];
        foreach ($blockStatements as $statement) {
            $pairs[] = [
                'variable' => $statement['variable']['name'],
                'expression' => $statement['expression']
            ];
        }
        return $pairs;
    }

    /**
     * Zavolá metodu dané třídy podle jména a arity.
     *
     * @param string $className Název třídy
     * @param string $methodName Název metody (selektor)
     * @param array $args Argumenty
     * @return SOLObject Výsledek volání
     */
    public function invokeMethod(string $className, string $methodName, array $args = []): SOLObject
    {
        $method = $this->findMethod($className, $methodName, count($args));

        if (!$method) {
            $this->writeError(
                "Method $methodName with arity " . count($args) . " not found in class $className",
                51
            );
        }

        $block = new SOLBlock(
            $method['statements'],
            $method['parameters'],
            count($method['parameters']),
            $this,
            $this->settings->getStdOutWriter(),
            $this->settings->getInputReader()
        );

        return $block->execute($args);
    }

    /**
     * Najde metodu podle třídy, selektoru a arity.
     *
     * @param string $className Název třídy
     * @param string $selector Selektor (jméno metody)
     * @param int $arity Počet argumentů
     * @return array Struktura metody
     */
    private function findMethod(string $className, string $selector, int $arity): array
    {
        if (isset($this->methodMap[$className][$selector])) {
            $method = $this->methodMap[$className][$selector];
            if ($method['arity'] === $arity) {
                return $method;
            }

            $this->writeError(
                "Method $selector with arity $arity not found in class $className",
                50
            );
        }
        $this->writeError(
            "Method $selector not found in class $className",
            50
        );
        return [];
    }

    /**
     * Vyhodnotí výraz v daném kontextu a s lokálními proměnnými.
     *
     * @param array $expr AST uzel výrazu
     * @param object $context Kontext (objekt/self)
     * @param array $locals Lokální proměnné
     * @return SOLObject Výsledek vyhodnocení
     */
    public function evaluateExpression(array $expr, object $context, array &$locals): SOLObject
    {
        if ($expr['type'] === 'variable' && $expr['name'] === 'self') {
            return new SOLObject(
                $this->settings->getStdOutWriter(),
                $this->settings->getInputReader(),
                "self"
            );
        }

        return match ($expr['type']) {
            'literal' => $this->createLiteral($expr),
            'variable' => $this->getVariable($expr['name'], $locals),
            'message_send' => $this->handleMessageSend($expr, $context, $locals),
            'block' => $this->createBlock($expr),
            default => $this->writeError("Invalid expression type", 53)
        };
    }

    /**
     * Zpracuje volání zprávy (message send) na objekt.
     *
     * @param array $sendExpr AST uzel zprávy
     * @param object $context Kontext
     * @param array $locals Lokální proměnné
     * @return SOLObject Výsledek volání
     */
    private function handleMessageSend(array $sendExpr, object $context, array &$locals): SOLObject
    {
        $receiver = $this->evaluateExpression($sendExpr['receiver'], $context, $locals);
        $args = array_map(fn($arg) => $this->evaluateExpression($arg, $context, $locals), $sendExpr['arguments']);

        return $this->dispatchMessage($receiver, $sendExpr['selector'], $args);
    }

    /**
     * Odesílá zprávu objektu, zajišťuje dynamické volání metod a správu atributů.
     *
     * @param object $receiver Objekt, kterému je zpráva určena
     * @param string $selector Selektor (název zprávy/metody)
     * @param array $args Argumenty zprávy
     * @return SOLObject Výsledek zprávy
     */
    private function dispatchMessage(object $receiver, string $selector, array $args): SOLObject
    {
        $methodName = str_replace(':', '', $selector);

        if ($receiver instanceof SOLObject && $receiver->getClassName() === 'self') {
            if ($this->isMethod($selector)) {
                foreach ($this->classMap as $className => $methods) {
                    for ($i = 0; $i < count($methods['methods']); $i++) {
                        if ($methods['methods'][$i] === $selector) {
                            $blok = new SOLBlock(
                                $this->methodMap[$className][$selector]['statements'],
                                $this->methodMap[$className][$selector]['parameters'],
                                count($this->methodMap[$className][$selector]['parameters']),
                                $this,
                                $this->settings->getStdOutWriter(),
                                $this->settings->getInputReader()
                            );
                            return $blok->execute($args);
                        }
                    }
                }
            } else {
                if (str_ends_with($selector, ':')) {
                    if (!isset($this->classAttributes['Main'])) {
                        $this->classAttributes['Main'] = [];
                    }

                    $this->classAttributes['Main'][$methodName] = $args[0];
                    return $args[0];
                } else {
                    if (!isset($this->classAttributes['Main'][$methodName])) {
                        $this->stderr->writeString("Unknown attribut $methodName");
                        exit(ReturnCode::INTERPRET_DNU_ERROR);
                    }
                    return $this->classAttributes['Main'][$methodName];
                }
            }
        }

        if (preg_match('/^(value)+$/', str_replace(':', '', $selector))) {
            $selector = 'value';
            $methodName = 'value';
        }

        if ($this->userMethod($selector)) {
            foreach ($this->classMap as $className => $methods) {
                for ($i = 0; $i < count($methods['methods']); $i++) {
                    if ($methods['methods'][$i] === $selector) {
                        $blok = new SOLBlock(
                            $this->methodMap[$className][$selector]['statements'],
                            $this->methodMap[$className][$selector]['parameters'],
                            count($this->methodMap[$className][$selector]['parameters']),
                            $this,
                            $this->settings->getStdOutWriter(),
                            $this->settings->getInputReader()
                        );
                        return $blok->execute($args);
                    }
                }
            }
        }

        if (!method_exists($receiver, $methodName)) {
            $this->writeError(
                "Object of class {$receiver->getClassName()}does not understand the message '$methodName'",
                51
            );
        }

        try {
            return $receiver->$methodName(...$args);
        } catch (\ArgumentCountError $e) {
            $this->writeError(("Invalid number of arguments for '$selector'"), 51);
            return new SOLNil(
                $this->settings->getStdOutWriter(),
                $this->settings->getInputReader()
            );
        }
    }

    /**
     * Zjistí, zda je selektor metoda.
     *
     * @param string $selector Selektor
     * @return bool
     */
    public function isMethod(string $selector): bool
    {
        foreach ($this->builtinClasses as $className => $methods) {
            for ($i = 1; $i < count($methods); $i++) {
                if ($methods[$i] === $selector) {
                    return  true;
                }
            }
        }

        if ($this->userMethod($selector)) {
            return true;
        }

        return  false;
    }

    /**
     * Zjistí, zda je selektor uživatelská metoda.
     *
     * @param string $selector Selektor
     * @return bool
     */
    public function userMethod(string $selector): bool
    {
        foreach ($this->classMap as $className => $methods) {
            for ($i = 0; $i < count($methods['methods']); $i++) {
                if ($methods['methods'][$i] === $selector) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Vytvoří blok (SOLBlock) z AST reprezentace.
     *
     * @param array $blockExpr AST blok
     * @return SOLBlock
     */
    private function createBlock(array $blockExpr): SOLBlock
    {
        return new SOLBlock(
            $this->getVarExpr($blockExpr),
            $blockExpr['parameters'],
            count($blockExpr['parameters']),
            $this,
            $this->settings->getStdOutWriter(),
            $this->settings->getInputReader()
        );
    }

    /**
     * Vytvoří literál (SOLInteger, SOLString, SOLNil, SOLTrue, SOLFalse) z AST uzlu.
     *
     * @param array $literal AST literál
     * @return object
     */
    private function createLiteral(array $literal): object
    {
        if ($literal['class'] === 'class') {
            return $this->instantiateBuiltinClass($literal['value']);
        }

        return match ($literal['class']) {
            'Integer' => new SOLInteger(
                $this->settings->getStdOutWriter(),
                $this->settings->getInputReader(),
                (int)$literal['value']
            ),
            'String' => new SOLString(
                $this->settings->getStdOutWriter(),
                $this->settings->getInputReader(),
                $literal['value']
            ),
            'Nil' => SOLNil::getInstance(
                $this->settings->getStdOutWriter(),
                $this->settings->getInputReader()
            ),
            'True' => SOLTrue::getInstance(
                $this->settings->getStdOutWriter(),
                $this->settings->getInputReader()
            ),
            'False' => SOLFalse::getInstance(
                $this->settings->getStdOutWriter(),
                $this->settings->getInputReader()
            ),
            default => $this->writeError("Invalid literal type", 53)
        };
    }

    /**
     * Vrátí hodnotu proměnné z lokálního kontextu.
     *
     * @param string $name Jméno proměnné
     * @param array $locals Lokální proměnné
     * @return object
     */
    private function getVariable(string $name, array $locals): object
    {
        if (isset($locals[$name])) {
            return $locals[$name];
        }

        $this->writeError("Unknown variable: $name", 52);

        return new SOLNil(
            $this->settings->getStdOutWriter(),
            $this->settings->getInputReader()
        );
    }

    /**
     * Vytvoří instanci vestavěné třídy podle jména.
     *
     * @param string $className Název třídy
     * @return object
     */
    private function instantiateBuiltinClass(string $className): object
    {
        $originalClass = $className;

        if (!isset($this->builtinClasses[$className])) {
            $parent = $this->classMap[$className]['parent'] ?? null;
            if ($parent !== null && isset($this->builtinClasses[$parent])) {
                $className = $parent;
            }
        }

        $stdout = $this->settings->getStdOutWriter();
        $stdin = $this->settings->getInputReader();

        return match ($className) {
            'Integer' => new SOLInteger($stdout, $stdin, 0, $originalClass),
            'String'  => new SOLString($stdout, $stdin, "", $originalClass),
            'Nil'     => SOLNil::getInstance($stdout, $stdin),
            'True'    => SOLTrue::getInstance($stdout, $stdin),
            'False'   => SOLFalse::getInstance($stdout, $stdin),
            default   => new SOLObject($stdout, $stdin)
        };
    }

    /**
     * Vypíše chybovou hlášku a ukončí interpretaci s daným kódem.
     *
     * @param string $message Chybová zpráva
     * @param int $code Návratový kód
     */
    public function writeError(string $message, int $code): void
    {
        $this->stderr->writeString($message);
        exit($code);
    }
}
