<?php


namespace Samsara\Mason\Tags;

use JetBrains\PhpStorm\Pure;
use Samsara\Mason\Tags\Base\DocBlockTag;

class DeprecatedTag extends DocBlockTag
{

    #[Pure]
    public function __construct(string $description, string $type = '', string $name = '')
    {
        parent::__construct('author', $description, $type, $name);
    }

}