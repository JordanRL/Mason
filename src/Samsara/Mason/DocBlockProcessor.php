<?php


namespace Samsara\Mason;

use Samsara\Mason\Tags\ExampleTag;
use Samsara\Mason\Tags\Base\DocBlockTag;

class DocBlockProcessor
{

    public string $summary = '';
    public string $description = '';
    public ?ExampleTag $example = null;
    /** @var DocBlockTag[]  */
    public array $params = [];
    /** @var DocBlockTag[]  */
    public array $authors = [];
    /** @var DocBlockTag[]  */
    public array $throws = [];
    public ?DocBlockTag $return = null;
    /** @var DocBlockTag[]  */
    public array $others = [];

    public function __construct(string $docBlock, bool $useSummary = true)
    {

        $lines = explode(PHP_EOL, $docBlock);

        $inSummary = $useSummary;
        $inDesc = !$useSummary;
        $inExample = false;

        $currSummary = '';
        $currContent = '';
        $currTag = '';
        $currName = '';
        $currType = '';

        foreach ($lines as $line) {
            if (str_contains($line, '/**') || str_contains($line, '*/')) {
                continue;
            }

            preg_match('/^[\s]*\*[^a-z|@]+(.*?)$/ism', $line, $matches);

            if (isset($matches[1]) && !empty($matches[1])) {
                $lineContent = $matches[1];
            } else {
                $lineContent = '';
            }

            if (!empty($currContent) && $inSummary) {
                $this->summary = $currContent;
                $inSummary = false;
                $currContent = '';
            }

            if (str_starts_with($lineContent, 'Example:')) {
                $this->description = trim($currContent);
                $currContent = '';
                $inExample = true;
                $inDesc = false;
                $inSummary = false;
                continue;
            }

            if (str_starts_with($lineContent, '@')) {
                if ($inExample || $inDesc || $inSummary) {
                    $this->summary = $inSummary ? trim($currContent) : $this->summary;
                    $this->description = $inDesc ? trim($currContent) : $this->description;
                    $this->example = $inExample ? (new ExampleTag('example', $currSummary))->setExampleCode($currContent) : $this->example;
                    $inExample = false;
                    $inDesc = false;
                    $inSummary = false;
                } else {
                    $this->pushTag($currTag, $currType, $currName, $currContent);
                }

                $currSummary = '';
                $currContent = '';
                $currTag = '';
                $currName = '';
                $currType = '';

                preg_match('/\@([^\s]+)/i', $lineContent, $matches);
                $currTag = strtolower($matches[1]);

                switch ($currTag) {
                    case 'param':
                        $extracted = $this->varTagProcessor($lineContent);
                        $currTag = $extracted['tag'];
                        $currType = $extracted['type'];
                        $currName = $extracted['name'];
                        $currContent = $extracted['desc'];
                        break;

                    case 'throws':
                    case 'return':
                        $extracted = $this->typeTagProcessor($lineContent);
                        $currTag = $extracted['tag'];
                        $currType = $extracted['type'];
                        $currContent = $extracted['desc'];
                        break;

                    case 'author':
                    default:
                        $extracted = $this->textTagProcessor($lineContent);
                        $currTag = $extracted['tag'];
                        $currContent = $extracted['desc'];
                        break;
                }

                if ($currTag == 'throws') {
                    $currType = explode('|', $currType);
                }
            } elseif (!$inExample) {
                if (!empty(trim($lineContent))) {
                    $currContent .= ' '.$lineContent;
                } else {
                    $currContent .= PHP_EOL.PHP_EOL;
                }
            } else {
                if (preg_match('/^[\s]*\* (.*?)$/ism', $line, $matches)) {
                    $currContent .= ' '.$matches[1];
                }
            }
            $this->pushTag($currTag, $currType, $currName, $currContent);
        }


    }

    protected function pushTag(string $tag, string|array $type, string $name, string $desc)
    {

        if (is_array($type)) {
            foreach ($type as $item) {
                 $this->pushTag($tag, $item, $name, $desc);
            }

            return;
        }

        $data = new DocBlockTag(
            $tag,
            $desc,
            $type,
            $name
        );

        if ($tag == "param") {
            $this->params[$name] = $data;
        } elseif ($tag == "throws") {
            $this->throws[] = $data;
        } elseif ($tag == "authors") {
            $this->authors[] = $data;
        } elseif ($tag == "return") {
            $this->return = $data;
        } else {
            $this->others[$tag] = $data;
        }

    }

    protected function varTagProcessor(string $tagInfo)
    {

        $matched = preg_match('/\@([^\s]+)([\s]+([^\s]+)([\s]+([^\s]+)(?:[\s]*(.+))?)?)?$/i', trim($tagInfo), $parts);

        if (!$matched) {
            throw new \LogicException('Error with RegEx on DocBlock line: '.$tagInfo);
        }

        if (count($parts) == 6) {
            $tag = strtolower($parts[1]);
            $type = str_contains($parts[3], '$') ? $parts[5] : $parts[3];
            $varName = str_contains($parts[3], '$') ? $parts[3] : $parts[5];
            $desc = '';
        } elseif (count($parts) == 4) {
            $tag = strtolower($parts[1]);
            $type = str_contains($parts[3], '$') ? '' : $parts[3];
            $varName = str_contains($parts[3], '$') ? $parts[3] : '';
            $desc = '';
        } elseif (count($parts) == 2) {
            $tag = strtolower($parts[1]);
            $type = '';
            $varName = '';
            $desc = '';
        } elseif (count($parts) == 7) {
            $tag = strtolower($parts[1]);
            $type = str_contains($parts[3], '$') ? $parts[5] : $parts[3];
            $varName = str_contains($parts[3], '$') ? $parts[3] : $parts[5];
            $desc = $parts[6];
        } else {
            throw new \LogicException('Error with RegEx on DocBlock line: '.$tagInfo);
        }

        return ['tag' => $tag, 'type' => $type, 'name' => $varName, 'desc' => $desc];

    }

    protected function typeTagProcessor(string $tagInfo)
    {

        $matched = preg_match('/\@([^\s]+)[\s]+([^\s]+)(?:[\s]+([^$]+))?$/i', trim($tagInfo), $parts);

        $tag = $parts[1];
        $type = $parts[2];
        $desc = $parts[3] ?? '';

        return ['tag' => $tag, 'type' => $type, 'desc' => $desc];
    }

    protected function textTagProcessor(string $tagInfo)
    {
        $matched = preg_match('/\@([^\s]+)(?:[\s]+([^$]+))?$/i', trim($tagInfo), $parts);

        $tag = $parts[1];
        $desc = $parts[2] ?? '';

        return ['tag' => $tag, 'desc' => $desc];
    }

    protected function boolTagProcessor(string $tagInfo)
    {

    }

}