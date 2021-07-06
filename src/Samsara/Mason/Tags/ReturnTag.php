<?php


namespace Samsara\Mason\Tags;

use Samsara\Mason\Tags\Base\DocBlockTag;
use JetBrains\PhpStorm\Pure;

class ReturnTag extends DocBlockTag
{

    #[Pure]
    public function __construct(string $description, string $type = '', string $name = '')
    {
        parent::__construct('author', $description, $type, $name);
    }

}