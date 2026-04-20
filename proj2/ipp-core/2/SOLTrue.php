<?php

namespace IPP\Student;

use IPP\Core\Interface\InputReader;
use IPP\Core\Interface\OutputWriter;

/**
 * SOLTrue
 * Singleton třída reprezentující logickou hodnotu true v SOL25. Obsahuje logické operace (and, or, not),
 * podmíněné větvení (ifTrueifFalse) a metody pro porovnání s jinými objekty.
 */

class SOLTrue extends SOLObject
{
    public static ?SOLTrue $instance = null;

    public function __construct(OutputWriter $output, InputReader $input, string $className = 'True')
    {
        parent::__construct($output, $input);
        $this->className = $className;
    }


    public static function getInstance(OutputWriter $output, InputReader $input): SOLTrue
    {
        if (self::$instance === null) {
            self::$instance = new self($output, $input);
        }
        return self::$instance;
    }

    public function identicalTo(SOLObject $object): SOLTrue|SOLFalse
    {
        return ($object instanceof SOLTrue) ? SOLTrue::getInstance($this->output, $this->input)
                                : SOLFalse::getInstance($this->output, $this->input);
    }

    public function equalTo(SOLObject $object): SOLTrue|SOLFalse
    {
        return $this->identicalTo($object);
    }

    public function new(): SOLTrue
    {
        return self::getInstance($this->output, $this->input);
    }

    public function from(): SOLTrue
    {
        return self::getInstance($this->output, $this->input);
    }

    public function not(): SOLFalse
    {
        return SOLFalse::getInstance($this->output, $this->input);
    }

    public function and(SOLObject $block): SOLTrue|SOLFalse
    {
        if ($block instanceof SOLTrue) {
            return SOLTrue::getInstance($this->output, $this->input);
        }
        return SOLFalse::getInstance($this->output, $this->input);
    }

    public function or(SOLObject $block): SOLTrue
    {
        return $this;
    }

    public function ifTrueifFalse(SOLBlock $trueBlock, SOLBlock $falseBlock): SOLObject
    {
        return $trueBlock->execute();
    }
}
