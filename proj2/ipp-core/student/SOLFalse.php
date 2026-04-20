<?php

namespace IPP\Student;

use IPP\Core\Interface\InputReader;
use IPP\Core\Interface\OutputWriter;

/**
 * SOLFalse
 * Singleton třída reprezentující logickou hodnotu false v SOL25. Obsahuje logické operace (and, or, not),
 * podmíněné větvení (ifTrueifFalse) a metody pro porovnání s jinými objekty.
 */

class SOLFalse extends SOLObject
{
    private static ?SOLFalse $instance = null;

    public function __construct(OutputWriter $output, InputReader $input, string $className = 'False')
    {
        parent::__construct($output, $input);
        $this->className = $className;
    }

    public static function getInstance(OutputWriter $output, InputReader $input): SOLFalse
    {
        if (self::$instance === null) {
            self::$instance = new self($output, $input);
        }
        return self::$instance;
    }

    public function identicalTo(SOLObject $object): SOLTrue|SOLFalse
    {
        return ($object instanceof SOLFalse) ? SOLTrue::getInstance($this->output, $this->input)
                                : SOLFalse::getInstance($this->output, $this->input);
    }

    public function equalTo(SOLObject $object): SOLTrue|SOLFalse
    {
        return $this->identicalTo($object);
    }

    public function new(): SOLFalse
    {
        return self::getInstance($this->output, $this->input);
    }

    public function from(): SOLFalse
    {
        return self::getInstance($this->output, $this->input);
    }

    public function not(): SOLTrue
    {
        return SOLTrue::getInstance($this->output, $this->input);
    }

    public function and(SOLObject $object): SOLFalse
    {
        return $this;
    }

    public function or(SOLObject $object): SOLTrue|SOLFalse
    {
        if ($object instanceof SOLTrue) {
            return SOLTrue::getInstance($this->output, $this->input);
        }
        return SOLFalse::getInstance($this->output, $this->input);
    }

    public function ifTrueifFalse(SOLBlock $trueBlock, SOLBlock $falseBlock): SOLObject
    {
        return $falseBlock->execute();
    }
}
