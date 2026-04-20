<?php

namespace IPP\Student;

use IPP\Core\Interface\InputReader;
use IPP\Core\Interface\OutputWriter;
use IPP\Core\ReturnCode;

/**
 * SOLInteger
 * Třída reprezentující celočíselné hodnoty v SOL25. Umožňuje aritmetické operace (sčítání, odčítání,
 * násobení, dělení), převod na řetězec a porovnávání s jinými celočíselnými objekty.
 */

class SOLInteger extends SOLObject
{
    private int $value;

    public function __construct(OutputWriter $output, InputReader $input, int $value = 0, string $className = 'Integer')
    {
        parent::__construct($output, $input);
        $this->className = $className;
        $this->value = $value;
    }

    public function new(): SOLInteger
    {
        return $this;
    }

    public function from(SOLObject $object): SOLInteger
    {
        if (!($object instanceof SOLInteger)) {
            exit(ReturnCode::INTERPRET_VALUE_ERROR);
        }
        $this->value = $object->value;
        return $this;
    }

    public function identicalTo(SOLObject $object): SOLTrue|SOLFalse
    {
        if (!($object instanceof SOLInteger)) {
            exit(ReturnCode::INTERPRET_VALUE_ERROR);
        }

        return $this->value === $object->value && $this->className === $object->className
            ? SOLTrue::getInstance($this->output, $this->input)
            : SOLFalse::getInstance($this->output, $this->input);
    }

    public function equalTo(SOLObject $object): SOLTrue|SOLFalse
    {
        if (!($object instanceof SOLInteger)) {
            exit(ReturnCode::INTERPRET_VALUE_ERROR);
        }

        return $this->value === $object->value
            ? SOLTrue::getInstance($this->output, $this->input)
            : SOLFalse::getInstance($this->output, $this->input);
    }

    public function greaterThan(SOLObject $object): SOLTrue|SOLFalse
    {
        if (!($object instanceof SOLInteger)) {
            exit(ReturnCode::INTERPRET_VALUE_ERROR);
        }
        return $this->value > $object->value ? SOLTrue::getInstance($this->output, $this->input)
                                             : SOLFalse::getInstance($this->output, $this->input);
    }

    public function plus(SOLObject $object): SOLInteger
    {
        if (!($object instanceof SOLInteger)) {
            exit(ReturnCode::INTERPRET_VALUE_ERROR);
        }
        return new SOLInteger($this->output, $this->input, $this->value + $object->value,);
    }

    public function minus(SOLObject $object): SOLInteger
    {
        if (!($object instanceof SOLInteger)) {
            exit(ReturnCode::INTERPRET_VALUE_ERROR);
        }
        return new SOLInteger($this->output, $this->input, $this->value - $object->value);
    }

    public function multiplyBy(SOLObject $object): SOLInteger
    {
        if (!($object instanceof SOLInteger)) {
            exit(ReturnCode::INTERPRET_VALUE_ERROR);
        }
        return new SOLInteger($this->output, $this->input, $this->value * $object->value);
    }

    public function divBy(SOLObject $object): SOLInteger
    {
        if (!($object instanceof SOLInteger)) {
            exit(ReturnCode::INTERPRET_VALUE_ERROR);
        }

        if ($object->value === 0) {
            exit(ReturnCode::INTERPRET_VALUE_ERROR);
        }

        return new SOLInteger($this->output, $this->input, (int)($this->value / $object->value));
    }

    public function asString(): SOLString
    {
        return new SOLString($this->output, $this->input, (string)$this->value);
    }

    public function timesRepeat(SOLBlock $block): SOLNil
    {
        if ($this->value > 0) {
            for ($i = 1; $i <= $this->value; $i++) {
                $block->send("value:", [new SOLInteger($this->output, $this->input, $i,)]);
            }
        }
        return new SOLNil($this->output, $this->input);
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function isNumber(): SOLTrue|SOLFalse
    {
        return SOLTrue::getInstance($this->output, $this->input);
    }
}
