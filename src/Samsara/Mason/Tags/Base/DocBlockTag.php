<?php


namespace Samsara\Mason\Tags\Base;


class DocBlockTag
{

    public function __construct(
        public string $tag,
        public string $description,
        public string $type = '',
        public string $name = ''
    )
    {

    }

}