<?php

namespace IPP\Student;

use IPP\Core\Interface\InputReader;
use IPP\Core\Interface\OutputWriter;

/**
 * SOLObject
 * Základní třída pro všechny objekty v SOL25. Obsahuje společné metody pro práci s objekty,
 * jako je porovnávání, převod na řetězec, kontrola typu, práce s atributy a zasílání zpráv.
 * Všechny ostatní třídy v SOL dědí od této třídy.
 */

class SOLObject
{
    protected string $className;
    protected OutputWriter $output;
    protected InputReader $input;

    public function __construct(OutputWriter $output, InputReader $input, string $className = 'Object')
    {
        $this->output = $output;
        $this->input = $input;
        $this->className = $className;
    }

    public function send(string $selector, array $args = []): SOLObject
    {
        $methodName = rtrim($selector, ':');
        if (method_exists($this, $methodName)) {
            try {
                return call_user_func_array([$this, $methodName], $args);
            } catch (\TypeError $e) {
                throw new \RuntimeException("Method $selector called with incompatible arguments ", 51);
            }
        }

        if (substr_count($selector, ':') === 0 && property_exists($this, $selector)) {
            return $this->$selector;
        }

        if (substr_count($selector, ':') === 1) {
            $this->$methodName = $args[0];
            return $this;
        }

        throw new \RuntimeException(
            "Object of class " . $this->getClassName() . " does not understand message $selector",
            51
        );
    }

    public function value(mixed $object): SOLObject
    {
        return $object;
    }

    public function new(): SOLObject
    {
        return $this;
    }

    public function identicalTo(SOLObject $object): SOLTrue|SOLFalse
    {
        return $this === $object ? SOLTrue::getInstance($this->output, $this->input)
                                : SOLFalse::getInstance($this->output, $this->input);
    }

    public function equalTo(SOLObject $object): SOLTrue|SOLFalse
    {
        return $this->identicalTo($object);
    }

    public function asString(): SOLString
    {
        return new SOLString($this->output, $this->input);
    }

    public function isNumber(): SOLTrue|SOLFalse
    {
        return SOLFalse::getInstance($this->output, $this->input);
    }
    public function isString(): SOLTrue|SOLFalse
    {
        return SOLFalse::getInstance($this->output, $this->input);
    }
    public function isBlock(): SOLTrue|SOLFalse
    {
        return SOLFalse::getInstance($this->output, $this->input);
    }
    public function isNil(): SOLTrue|SOLFalse
    {
        return SOLFalse::getInstance($this->output, $this->input);
    }
    public function getClassName(): string
    {
        return $this->className;
    }
}
