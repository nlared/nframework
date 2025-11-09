<?php

class XMLS implements ArrayAccess
{
    public $tagName = '';
    public $className = '';
    public $attributes = [];
    public $addattributes = '';
    public $containervar = '';
    public $_sequence = [];

    public function __construct(array $ops = [])
    {
        $this->className = static::class;

        foreach ($ops as $property => $value) {
            if (property_exists($this, $property)) {
                $this->{$property} = $value;
            }
        }

        if (!empty($this->containervar)) {
            $this->{$this->containervar} = [];
        }
    }

    private function encodeSpecial(string $value): string
    {
        // Use htmlspecialchars for proper XML escaping
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    public function __toString(): string
    {
        $attributes = [];
        $elements = [];
        $data = get_object_vars($this);

        $specialVars = [
            'attributes',
            'className',
            'tagName',
            'addattributes',
            'containervar',
            '_sequence'
        ];

        foreach ($data as $name => $value) {
            if (in_array($name, $specialVars)) {
                continue;
            }

            if (in_array($name, $this->attributes)) {
                if ($value !== '' && $value !== null) {
                    $attributes[] = $name . '="' . $this->encodeSpecial((string)$value) . '"';
                }
            } else {
                if ($value !== '' && $value !== null) {
                    $elementValue = is_array($value) ? implode("\n", $value) : (string)$value;

                    if (empty($this->_sequence)) {
                        $elements[] = $elementValue;
                    } else {
                        $sequenceIndex = array_search($name, $this->_sequence, true);
                        if ($sequenceIndex !== false) {
                            $elements[$sequenceIndex] = $elementValue;
                        } else {
                            $elements[] = $elementValue;
                        }
                    }
                }
            }
        }

        ksort($elements, SORT_NUMERIC);

        $attributeString = '';
        if (!empty($this->addattributes)) {
            $attributeString .= ' ' . $this->addattributes;
        }
        if (!empty($attributes)) {
            $attributeString .= ' ' . implode(' ', $attributes);
        }

        if (empty($elements)) {
            return "<{$this->tagName}{$attributeString}/>";
        }

        return "<{$this->tagName}{$attributeString}>\n" .
            implode("\n", $elements) .
            "\n</{$this->tagName}>";
    }

    public function deserialize(DOMElement $xml, array $namespaceTranslations = [], array $classTranslations = []): void
    {
        $this->tagName = $xml->tagName;

        // Set attributes
        foreach ($xml->attributes as $attribute) {
            $this->{$attribute->name} = $attribute->value;
        }

        // Process child nodes
        foreach ($xml->childNodes as $node) {
            if (!($node instanceof DOMElement) || empty($node->localName)) {
                continue;
            }

            $className = '\\' . str_replace(':', '\\', $node->nodeName);

            // Apply namespace translations
            foreach ($namespaceTranslations as $from => $to) {
                if (str_starts_with($className, $from)) {
                    $className = str_replace($from, $to, $className);
                    break;
                }
            }

            // Apply class translations
            $className = $classTranslations[$className] ?? $className;

            if (class_exists($className)) {
                $object = new $className();
                if (method_exists($object, 'deserialize')) {
                    $object->deserialize($node, $namespaceTranslations, $classTranslations);
                }

                $propertyName = $node->localName;
                if (isset($this->{$propertyName}) && is_array($this->{$propertyName})) {
                    $this->{$propertyName}[] = $object;
                } else {
                    $this->{$propertyName} = $object;
                }
            } else {
                error_log("Class $className does not exist");
            }
        }
    }

    // ArrayAccess implementation
    public function offsetSet($offset, $value): void
    {
        if (empty($this->containervar)) {
            throw new RuntimeException('Container variable not set');
        }

        if ($offset === null) {
            $this->{$this->containervar}[] = $value;
        } else {
            $this->{$this->containervar}[$offset] = $value;
        }
    }

    public function offsetExists($offset): bool
    {
        if (empty($this->containervar)) {
            return false;
        }

        return isset($this->{$this->containervar}[$offset]);
    }

    public function offsetUnset($offset): void
    {
        if (!empty($this->containervar) && isset($this->{$this->containervar}[$offset])) {
            unset($this->{$this->containervar}[$offset]);
        }
    }

    public function offsetGet($offset): mixed
    {
        if (empty($this->containervar)) {
            return null;
        }

        return $this->{$this->containervar}[$offset] ?? null;
    }
}
