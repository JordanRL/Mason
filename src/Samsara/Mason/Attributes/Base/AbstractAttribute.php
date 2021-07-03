<?php


namespace Samsara\Mason\Attributes\Base;


abstract class AbstractAttribute
{

    private string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

}