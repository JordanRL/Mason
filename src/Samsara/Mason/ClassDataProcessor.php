<?php


namespace Samsara\Mason;


class ClassDataProcessor
{

    private DocBlockProcessor $docBlock;
    private AttributeProcessor $attributes;

    public function __construct(\ReflectionClass $class)
    {
        $this->docBlock = new DocBlockProcessor($class->getDocComment());
        $this->attributes = new AttributeProcessor($class->getAttributes());
    }

}