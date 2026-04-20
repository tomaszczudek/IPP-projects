<?php

namespace IPP\Student;

use IPP\Core\Interface\InputReader;
use IPP\Core\Interface\OutputWriter;

/**
 * SOLBlock
 * Třída reprezentující bloky (lambda funkce, anonymní funkce) v SOL25. Bloky mohou mít parametry,
 * vlastní lokální proměnné a lze je volat s různým počtem argumentů. Umožňují například cykly,
 * podmínky a vyšší funkcionální operace.
 */

class SOLBlock extends SOLObject
{
    /**
     * @var array<array{variable: string, expression: mixed}> $statements
     */
    private array $statements;

    /**
     * @var array<array{name: string, order: int}> $parameters
     */
    private array $parameters;
    private int $arity;
    private Interpreter $interpreter;

     /**
     * @param array<array{variable: string, expression: mixed}> $statements
     * @param array<array{name: string, order: int}> $parameters
     */
    public function __construct(
        array $statements,
        array $parameters,
        int $arity,
        Interpreter $interpreter,
        OutputWriter $output,
        InputReader $input
    ) {
        parent::__construct($output, $input);
        $this->statements = $statements;
        $this->parameters = $parameters;
        $this->arity = $arity;
        $this->interpreter = $interpreter;
    }

    /**
     * @param array<int, SOLObject> $args
     */
    public function execute(array $args = []): SOLObject
    {
        if (count($args) != $this->arity) {
            $this->interpreter->writeError("Invalid number of arguments", 51);
        }
        $locals = [];
        $locals['self'] = $this;

        for ($i = 0; $i < count($this->parameters); $i++) {
            $paramName = $this->parameters[$i]['name'];

            if (isset($args[$i])) {
                $locals[$paramName] = $args[$i];
            } else {
                $this->interpreter->writeError("Unknown variable: $paramName", 52);
            }
        }

        $result = SOLNil::getInstance($this->output, $this->input);
        foreach ($this->statements as $statement) {
            $varName = $statement['variable'];
            $expr = $statement['expression'];

            $value = $this->interpreter->evaluateExpression($expr, $this, $locals);
            $locals[$varName] = $value;
            $result = $value;
        }

        return $result;
    }


    public function value(mixed $args = []): SOLObject
    {
        if (is_array($args)) {
            return $this->execute($args);
        }

        return $this->execute([$args]);
    }

    public function whileTrue(SOLBlock $block): SOLObject
    {
        $return = $block->execute();
        while ($return instanceof SOLTrue) {
            $return = $block->execute();
            $return = $block->execute();
        }

        return $this;
    }


    public function isBlock(): SOLTrue|SOLFalse
    {
        return SOLTrue::getInstance($this->output, $this->input);
    }
}
