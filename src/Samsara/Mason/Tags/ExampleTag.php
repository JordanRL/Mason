<?php

namespace Samsara\Mason\Tags;

use Samsara\Mason\Tags\Base\DocBlockTag;

class ExampleTag extends DocBlockTag
{

    public string $exampleCode = '';

    public function setExampleCode(string $exampleCode): self
    {
        $this->exampleCode = $exampleCode;

        return $this;
    }

    public function getExampleCodeRaw(): string
    {
        return $this->exampleCode;
    }

    public function getExampleCodeMDEscaped(): string
    {
        return '```php'.PHP_EOL.$this->exampleCode.PHP_EOL.'```';
    }

}