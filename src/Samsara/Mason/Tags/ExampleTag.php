<?php

namespace Samsara\Mason\Tags;

use Samsara\Mason\Tags\Base\DocBlockTag;

class ExampleTag extends DocBlockTag
{
    public function __construct(string $description, string $type = '', string $name = '')
    {
        parent::__construct('example', $description, $type, $name);
    }

    public string $exampleCode = '';

    public function setExampleCode(string $exampleCode): self
    {
        $lines = explode(PHP_EOL, $exampleCode);

        foreach ($lines as &$line) {
            $line = '    '.$line;
        }

        $exampleCode = implode(PHP_EOL, $lines);

        $this->exampleCode = $exampleCode;

        return $this;
    }

    public function getExampleCodeRaw(): string
    {
        return $this->exampleCode;
    }

    public function getExampleCodeMDEscaped(): string
    {
        return '```php'.PHP_EOL.$this->exampleCode.PHP_EOL.'    ```';
    }

}