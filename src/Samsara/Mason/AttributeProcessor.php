<?php


namespace Samsara\Mason;


use ReflectionAttribute;

class AttributeProcessor
{

    private array $attributes;

    /**
     * AttributeProcessor constructor.
     * @param ReflectionAttribute[] $attributes
     */
    public function __construct(array $attributes)
    {

        foreach ($attributes as $attribute) {
            if ($attribute->isRepeated()) {
                $this->attributes[$attribute->getName()][] = $attribute->getArguments();
            } else {
                $this->attributes[$attribute->getName()] = $attribute->getArguments();
            }
        }

    }

}