<?php

namespace IPP\Student;

use IPP\Core\Interface\InputReader;
use IPP\Core\Interface\OutputWriter;
use IPP\Core\ReturnCode;

/**
 * SOLString
 * Třída reprezentující řetězce v SOL25. Umožňuje práci s textovými hodnotami, jejich spojování,
 * převod na číslo, tisk na výstup, čtení ze vstupu a další běžné operace s řetězci.
 */
class SOLString extends SOLObject
{
    private string $value;

    public function __construct(
        OutputWriter $output,
        InputReader $input,
        string $value = "",
        string $className = "String"
    ) {
        parent::__construct($output, $input);
        $this->className = $className;
        $this->value = $value;
    }

    public function new(): SOLString
    {
        return $this;
    }

    public function from(SOLObject $object): SOLString
    {
        if (!($object instanceof SOLString)) {
            exit(ReturnCode::INTERPRET_VALUE_ERROR);
        }
        $this->value = $object->value;
        return $this;
    }

    public function print(): SOLString
    {
        $this->output->writeString($this->value);
        return $this;
    }

    public function read(): SOLString
    {
        $this->value = $this->input->readString();
        return $this;
    }

    public function identicalTo(SOLObject $object): SOLTrue|SOLFalse
    {
        return ($object instanceof SOLString && $this->className == $object->className)
        ? SOLTrue::getInstance($this->output, $this->input)
        : SOLFalse::getInstance($this->output, $this->input);
    }

    public function equalTo(SOLObject $object): SOLTrue|SOLFalse
    {
        if ($object instanceof SOLString) {
            return $this->value === $object->value ? SOLTrue::getInstance($this->output, $this->input)
                                                    : SOLFalse::getInstance($this->output, $this->input);
        }
        return SOLFalse::getInstance($this->output, $this->input);
    }

    public function asString(): SOLString
    {
        return $this;
    }

    public function asInteger(): SOLObject
    {
        if (is_numeric($this->value)) {
            return new SOLInteger($this->output, $this->input, (int)$this->value);
        }
        return  SOLNil::getInstance($this->output, $this->input);
    }

    public function concatenateWith(SOLObject $object): SOLString|SOLNil
    {
        if (!($object instanceof SOLString)) {
            return SOLNil::getInstance($this->output, $this->input);
        }

        return new SOLString($this->output, $this->input, $this->value . $object->value,);
    }

    public function startsWithendsBefore(SOLObject $start, SOLObject $end): SOLString|SOLNil
    {
        if (!($start instanceof SOLInteger && $end instanceof SOLInteger)) {
            exit(ReturnCode::INTERPRET_VALUE_ERROR);
        }

        $startIndex = $start->getValue();
        $endIndex = $end->getValue();

        if ($startIndex < 1 || $endIndex < 1) {
            return SOLNil::getInstance($this->output, $this->input);
        }

        if ($endIndex - $startIndex <= 0) {
            return new SOLString($this->output, $this->input);
        }

        $substr = substr($this->value, $startIndex - 1, $endIndex - $startIndex);
        return new SOLString($this->output, $this->input, $substr);
    }

    public function isString(): SOLTrue|SOLFalse
    {
        return SOLTrue::getInstance($this->output, $this->input);
    }
}
