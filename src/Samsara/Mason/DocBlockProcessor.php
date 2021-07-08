<?php


namespace Samsara\Mason;

use Samsara\Mason\Tags\AuthorTag;
use Samsara\Mason\Tags\DeprecatedTag;
use Samsara\Mason\Tags\ExampleTag;
use Samsara\Mason\Tags\Base\DocBlockTag;
use Samsara\Mason\Tags\GenericTag;
use Samsara\Mason\Tags\InternalTag;
use Samsara\Mason\Tags\LicenseTag;
use Samsara\Mason\Tags\PackageTag;
use Samsara\Mason\Tags\ParamTag;
use Samsara\Mason\Tags\ReturnTag;
use Samsara\Mason\Tags\SeeTag;
use Samsara\Mason\Tags\SinceTag;
use Samsara\Mason\Tags\SubPackageTag;
use Samsara\Mason\Tags\ThrowsTag;

class DocBlockProcessor
{

    public string $summary = '';
    public string $description = '';
    /** @var DocBlockTag[][] */
    protected array $tags = [];

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
                    if ($inExample) {
                        $this->pushTag('example', '', '', $currSummary);
                        /** @var ExampleTag $example */
                        $example = $this->getLastTag('example');
                        $example->setExampleCode($currContent);
                    }
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

                    case 'ignore':


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
                if (!empty($currContent)) {
                    $currContent .= PHP_EOL;
                }
                if (preg_match('/^[\s]*\* (.*?)$/ism', $line, $matches)) {
                    $currContent .= $matches[1];
                }
            }
            if ($currTag != "method") {
                $this->pushTag($currTag, $currType, $currName, $currContent);
            }
        }


    }

    public function hasTag(string $tag): bool
    {
        return array_key_exists($tag, $this->tags);
    }

    public function hasTagIndex(string $tag, int $index): bool
    {
        return array_key_exists($tag, $this->tags) && array_key_exists($index, $this->tags[$tag]);
    }

    public function getTagCount(string $tag): int
    {
        return count($this->tags[$tag]);
    }

    public function getTag(string $tag): array|null
    {
        if ($this->hasTag($tag)) {
            return $this->tags[$tag];
        }

        return null;
    }

    public function getLastTag(string $tag): DocBlockTag|null
    {
        if ($this->hasTag($tag)) {
            $lastKey = array_key_last($this->tags[$tag]);
            return $this->getTag($tag)[$lastKey];
        }

        return null;
    }

    public function getTagIndex(string $tag, $index = 0): DocBlockTag|null
    {
        if ($this->hasTagIndex($tag, $index)) {
            return $this->getTag($tag)[$index];
        }

        return null;
    }

    protected function pushTag(string $tag, string|array $type, string $name, string $desc)
    {

        if (is_array($type)) {
            foreach ($type as $item) {
                 $this->pushTag($tag, $item, $name, $desc);
            }

            return;
        }

        $data = [
            $desc,
            $type,
            $name
        ];
        $tagObject = match ($tag) {
            'param' => new ParamTag(...$data),
            'throws' => new ThrowsTag(...$data),
            'author' => new AuthorTag(...$data),
            'deprecated' => new DeprecatedTag(...$data),
            'example' => new ExampleTag(...$data),
            'internal' => new InternalTag(...$data),
            'license' => new LicenseTag(...$data),
            'package' => new PackageTag(...$data),
            'subpackage', 'sub-package' => new SubPackageTag(...$data),
            'return' => new ReturnTag(...$data),
            'see', 'seealso', 'see-also' => new SeeTag(...$data),
            'since' => new SinceTag(...$data),
            default => new GenericTag($tag, ...$data)
        };

        $this->tags[$tag][] = $tagObject;

    }

    protected function varTagProcessor(string $tagInfo)
    {

        $matched = preg_match('/\@([^\s]+)([\s]+([^\s]+)([\s]+([^\s]+)(?:[\s]*(.+))?)?)?$/ism', trim($tagInfo), $parts);

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

        $matched = preg_match('/\@([^\s]+)([\s]+(([^\s]+)(?:[\s]+(.+))?)?)?$/ism', trim($tagInfo), $parts);

        $tag = $parts[1] ?? '';
        $type = $parts[4] ?? '';
        $desc = $parts[3] ?? '';

        return ['tag' => $tag, 'type' => $type, 'desc' => $desc];
    }

    protected function textTagProcessor(string $tagInfo)
    {
        $matched = preg_match('/\@([^\s]+)(?:([\s]+(.+)?))?$/ism', trim($tagInfo), $parts);

        $tag = $parts[1] ?? '';
        $desc = $parts[2] ?? '';

        return ['tag' => $tag, 'desc' => $desc];
    }

    protected function boolTagProcessor(string $tagInfo)
    {

    }

}