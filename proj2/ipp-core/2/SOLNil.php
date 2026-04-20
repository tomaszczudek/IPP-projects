<?php

namespace IPP\Student;

use IPP\Core\Interface\InputReader;
use IPP\Core\Interface\OutputWriter;

/**
 * SOLNil
 * Singleton třída reprezentující hodnotu nil (prázdný objekt, ekvivalent null) v SOL25.
 * Používá se pro označení neexistující hodnoty nebo chybějícího výsledku.
 */

class SOLNil extends SOLObject
{
    private static ?SOLNil $instance = null;

    public function __construct(OutputWriter $output, InputReader $input, string $className = "Nil")
    {
        parent::__construct($output, $input);
        $this->className = $className;
    }

    public static function getInstance(OutputWriter $output, InputReader $input): SOLNil
    {
        if (self::$instance === null) {
            self::$instance = new self($output, $input);
        }
        return self::$instance;
    }

    public function identicalTo(SOLObject $object): SOLTrue|SOLFalse
    {
        return ($object instanceof SOLNil) ? SOLTrue::getInstance($this->output, $this->input)
                                : SOLFalse::getInstance($this->output, $this->input);
    }

    public function equalTo(SOLObject $object): SOLTrue|SOLFalse
    {
        return $this->identicalTo($object);
    }

    public function new(): SOLNil
    {
        return self::getInstance($this->output, $this->input);
    }

    public function from(): SOLNil
    {
        return self::getInstance($this->output, $this->input);
    }

    public function isNil(): SOLTrue|SOLFalse
    {
        return SOLTrue::getInstance($this->output, $this->input);
    }

    public function asString(): SOLString
    {
        return new SOLString($this->output, $this->input, "nil");
    }
}
