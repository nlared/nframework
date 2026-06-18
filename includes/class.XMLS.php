<?php

class XMLS implements ArrayAccess
{
    public $tagName = '';
    public $className = '';
    public $attributes = [];
    public $addattributes = '';
    public $containervar = '';
    public $_sequence = [];
    public $lastSerializationError = '';

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

    private static function normalizeIdentifier(string $name): string
    {
        $normalized = preg_replace('/[^a-zA-Z0-9_]/', '_', $name) ?? '';
        if ($normalized === '' || ctype_digit($normalized[0])) {
            $normalized = 'n_' . $normalized;
        }

        return $normalized;
    }

    private static function normalizeClassName(string $name): string
    {
        $normalized = preg_replace('/[^a-zA-Z0-9]+/', ' ', $name) ?? '';
        $normalized = str_replace(' ', '', ucwords(strtolower(trim($normalized))));
        if ($normalized === '') {
            return 'GeneratedElement';
        }

        if (ctype_digit($normalized[0])) {
            return 'X' . $normalized;
        }

        return $normalized;
    }

    private static function parseComplexType(DOMElement $complexType, DOMXPath $xpath): array
    {
        $attributes = [];
        $sequence = [];
        $repeatable = [];

        foreach ($xpath->query('./xs:attribute', $complexType) as $attributeNode) {
            if (!($attributeNode instanceof DOMElement)) {
                continue;
            }

            $attributeName = $attributeNode->getAttribute('name');
            if ($attributeName === '') {
                $attributeRef = $attributeNode->getAttribute('ref');
                if ($attributeRef !== '') {
                    $parts = explode(':', $attributeRef);
                    $attributeName = end($parts) ?: '';
                }
            }

            if ($attributeName !== '') {
                $attributes[] = self::normalizeIdentifier($attributeName);
            }
        }

        foreach ($xpath->query('./xs:sequence/xs:element', $complexType) as $elementNode) {
            if (!($elementNode instanceof DOMElement)) {
                continue;
            }

            $childName = $elementNode->getAttribute('name');
            if ($childName === '') {
                $childRef = $elementNode->getAttribute('ref');
                if ($childRef !== '') {
                    $parts = explode(':', $childRef);
                    $childName = end($parts) ?: '';
                }
            }

            if ($childName === '') {
                continue;
            }

            $propertyName = self::normalizeIdentifier($childName);
            $sequence[] = $propertyName;

            $maxOccurs = $elementNode->getAttribute('maxOccurs');
            if ($maxOccurs === 'unbounded' || ($maxOccurs !== '' && (int)$maxOccurs > 1)) {
                $repeatable[$propertyName] = true;
            }
        }

        return [
            'attributes' => array_values(array_unique($attributes)),
            'sequence' => $sequence,
            'repeatable' => $repeatable,
        ];
    }

    private static function formatLibxmlErrors(array $errors): string
    {
        if (empty($errors)) {
            return 'Unknown XML parsing error';
        }

        $lines = [];
        foreach ($errors as $error) {
            if (!($error instanceof LibXMLError)) {
                continue;
            }

            $message = trim($error->message);
            $line = (int)$error->line;
            $column = (int)$error->column;
            $lines[] = $message . " (line {$line}, column {$column})";
        }

        return empty($lines) ? 'Unknown XML parsing error' : implode('; ', $lines);
    }

    private static function sanitizeForXmlComment(string $message): string
    {
        $sanitized = trim($message);
        if ($sanitized === '') {
            return 'Unknown XML serialization error';
        }

        // XML comments cannot contain "--" and should avoid angle brackets from raw error text.
        $sanitized = str_replace(['--', '<', '>'], ['- -', '[', ']'], $sanitized);

        return $sanitized;
    }

    private function stringifyXmlValue($value, string $propertyName): string
    {
        if (is_array($value)) {
            $items = [];
            foreach ($value as $index => $item) {
                if ($item === '' || $item === null) {
                    continue;
                }

                $items[] = $this->stringifyXmlValue($item, $propertyName . '[' . $index . ']');
            }

            return implode("\n", $items);
        }

        if ($value instanceof DOMNode) {
            $ownerDocument = $value->ownerDocument;
            if (!($ownerDocument instanceof DOMDocument)) {
                throw new RuntimeException("Property {$propertyName} contains a DOMNode without ownerDocument");
            }

            $nodeXml = $ownerDocument->saveXML($value);
            if ($nodeXml === false) {
                throw new RuntimeException("Property {$propertyName} contains a DOMNode that cannot be serialized");
            }

            return $nodeXml;
        }

        if (is_object($value)) {
            if (!method_exists($value, '__toString')) {
                $type = get_class($value);
                throw new RuntimeException("Property {$propertyName} contains non-stringable object {$type}");
            }
        }

        return (string)$value;
    }

    public function toXmlString(): string
    {
        $this->lastSerializationError = '';

        if (trim((string)$this->tagName) === '') {
            $this->lastSerializationError = 'tagName is empty, cannot build XML root element';
            throw new RuntimeException($this->lastSerializationError);
        }

        $attributes = [];
        $elements = [];
        $data = get_object_vars($this);

        $specialVars = [
            'attributes' => true,
            'className' => true,
            'tagName' => true,
            'addattributes' => true,
            'containervar' => true,
            '_sequence' => true,
            'lastSerializationError' => true,
        ];

        $attributeLookup = [];
        foreach ($this->attributes as $attributeName) {
            $attributeLookup[$attributeName] = true;
        }

        $sequenceLookup = [];
        foreach ($this->_sequence as $sequenceIndex => $sequenceName) {
            $sequenceLookup[$sequenceName] = $sequenceIndex;
        }

        foreach ($data as $name => $value) {
            if (isset($specialVars[$name])) {
                continue;
            }

            if (isset($attributeLookup[$name])) {
                if ($value !== '' && $value !== null) {
                    if (is_array($value)) {
                        $this->lastSerializationError = "Attribute {$name} cannot be an array";
                        throw new RuntimeException($this->lastSerializationError);
                    }

                    $attributeValue = $this->stringifyXmlValue($value, $name);
                    $attributes[] = $name . '="' . $this->encodeSpecial($attributeValue) . '"';
                }
            } else {
                if ($value !== '' && $value !== null) {
                    $elementValue = $this->stringifyXmlValue($value, $name);
                    if ($elementValue === '') {
                        continue;
                    }

                    if (empty($sequenceLookup)) {
                        $elements[] = $elementValue;
                    } else {
                        if (isset($sequenceLookup[$name])) {
                            $elements[$sequenceLookup[$name]] = $elementValue;
                        } else {
                            $elements[] = $elementValue;
                        }
                    }
                }
            }
        }

        if (!empty($sequenceLookup) && count($elements) > 1) {
            ksort($elements, SORT_NUMERIC);
        }

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

    private static function elementNodeName(DOMElement $elementNode): string
    {
        $elementName = $elementNode->getAttribute('name');
        if ($elementName !== '') {
            return $elementName;
        }

        $elementRef = $elementNode->getAttribute('ref');
        if ($elementRef !== '') {
            $parts = explode(':', $elementRef);
            return end($parts) ?: '';
        }

        return '';
    }

    private static function resolveComplexTypeForElement(
        DOMElement $elementNode,
        DOMXPath $xpath,
        array $complexTypeMap
    ): ?DOMElement {
        foreach ($xpath->query('./xs:complexType', $elementNode) as $inlineComplexTypeNode) {
            if ($inlineComplexTypeNode instanceof DOMElement) {
                return $inlineComplexTypeNode;
            }
        }

        $typeName = $elementNode->getAttribute('type');
        if ($typeName !== '') {
            $parts = explode(':', $typeName);
            $localTypeName = end($parts) ?: $typeName;
            if (isset($complexTypeMap[$localTypeName])) {
                return $complexTypeMap[$localTypeName];
            }
        }

        return null;
    }

    private static function collectElementDefinitions(
        DOMElement $elementNode,
        DOMXPath $xpath,
        array $complexTypeMap,
        array $parentPath,
        array &$definitions,
        int $depth,
        int $maxDepth
    ): void {
        if ($depth > $maxDepth) {
            return;
        }

        $elementName = self::elementNodeName($elementNode);
        if ($elementName === '') {
            return;
        }

        $currentPath = $parentPath;
        $currentPath[] = $elementName;
        $pathKey = implode('/', $currentPath);
        if (isset($definitions[$pathKey])) {
            return;
        }

        $complexTypeNode = self::resolveComplexTypeForElement($elementNode, $xpath, $complexTypeMap);
        $parsed = [
            'attributes' => [],
            'sequence' => [],
            'repeatable' => [],
        ];

        if ($complexTypeNode instanceof DOMElement) {
            $parsed = self::parseComplexType($complexTypeNode, $xpath);
        }

        $definitions[$pathKey] = [
            'elementName' => $elementName,
            'classBaseName' => self::normalizeClassName(implode('_', $currentPath)),
            'parsed' => $parsed,
        ];

        if (!($complexTypeNode instanceof DOMElement)) {
            return;
        }

        foreach ($xpath->query('./xs:sequence/xs:element|./xs:choice/xs:element|./xs:all/xs:element', $complexTypeNode) as $childElementNode) {
            if (!($childElementNode instanceof DOMElement)) {
                continue;
            }

            self::collectElementDefinitions(
                $childElementNode,
                $xpath,
                $complexTypeMap,
                $currentPath,
                $definitions,
                $depth + 1,
                $maxDepth
            );
        }
    }

    public static function generateClassesFromXsd(
        string $xsdPath,
        string $outputDir,
        string $namespace = 'Generated\\XML',
        array $options = []
    ): array {
        if (!is_file($xsdPath)) {
            throw new InvalidArgumentException("XSD file not found: {$xsdPath}");
        }

        $overwrite = (bool)($options['overwrite'] ?? false);
        $recursiveElements = (bool)($options['recursiveElements'] ?? false);
        $maxDepth = (int)($options['maxDepth'] ?? 12);

        if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
            throw new RuntimeException("Cannot create output directory: {$outputDir}");
        }

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        $previousUseInternalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();
        if (!$dom->load($xsdPath)) {
            $parseErrors = self::formatLibxmlErrors(libxml_get_errors());
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseInternalErrors);
            throw new RuntimeException("Cannot parse XSD file: {$xsdPath}. {$parseErrors}");
        }
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('xs', 'http://www.w3.org/2001/XMLSchema');

        $complexTypeMap = [];
        foreach ($xpath->query('/xs:schema/xs:complexType[@name]') as $complexTypeNode) {
            if (!($complexTypeNode instanceof DOMElement)) {
                continue;
            }

            $complexTypeName = $complexTypeNode->getAttribute('name');
            if ($complexTypeName !== '') {
                $complexTypeMap[$complexTypeName] = $complexTypeNode;
            }
        }

        $definitions = [];
        foreach ($xpath->query('/xs:schema/xs:element[@name]') as $rootElementNode) {
            if (!($rootElementNode instanceof DOMElement)) {
                continue;
            }

            if ($recursiveElements) {
                self::collectElementDefinitions(
                    $rootElementNode,
                    $xpath,
                    $complexTypeMap,
                    [],
                    $definitions,
                    0,
                    $maxDepth
                );
                continue;
            }

            $elementName = self::elementNodeName($rootElementNode);
            if ($elementName === '') {
                continue;
            }

            $complexTypeNode = self::resolveComplexTypeForElement($rootElementNode, $xpath, $complexTypeMap);
            $parsed = [
                'attributes' => [],
                'sequence' => [],
                'repeatable' => [],
            ];

            if ($complexTypeNode instanceof DOMElement) {
                $parsed = self::parseComplexType($complexTypeNode, $xpath);
            }

            $definitions[$elementName] = [
                'elementName' => $elementName,
                'classBaseName' => self::normalizeClassName($elementName),
                'parsed' => $parsed,
            ];
        }

        $generatedFiles = [];
        $usedClassNames = [];
        foreach ($definitions as $definition) {
            $elementName = $definition['elementName'];
            $parsed = $definition['parsed'];

            $className = $definition['classBaseName'];
            if (isset($usedClassNames[$className])) {
                $usedClassNames[$className]++;
                $className .= (string)$usedClassNames[$className];
            } else {
                $usedClassNames[$className] = 1;
            }

            $filePath = rtrim($outputDir, '/') . '/' . $className . '.php';

            if (is_file($filePath) && !$overwrite) {
                continue;
            }

            $propertyLines = [];
            foreach ($parsed['attributes'] as $attributeName) {
                $propertyLines[] = "    public \${$attributeName} = '';";
            }

            foreach ($parsed['sequence'] as $elementPropertyName) {
                if (isset($parsed['repeatable'][$elementPropertyName])) {
                    $propertyLines[] = "    public \${$elementPropertyName} = [];";
                } else {
                    $propertyLines[] = "    public \${$elementPropertyName} = '';";
                }
            }

            $attributesExport = var_export(array_values($parsed['attributes']), true);
            $sequenceExport = var_export(array_values($parsed['sequence']), true);
            $propertyBlock = empty($propertyLines) ? '' : implode("\n", $propertyLines) . "\n\n";

            $code = "<?php\n\n";
            $code .= "namespace {$namespace};\n\n";
            $code .= "class {$className} extends \\XMLS\n";
            $code .= "{\n";
            $code .= "    public \$tagName = '{$elementName}';\n";
            $code .= "    public \$attributes = {$attributesExport};\n";
            $code .= "    public \$_sequence = {$sequenceExport};\n\n";
            $code .= $propertyBlock;
            $code .= "    public function __construct(array \$ops = [])\n";
            $code .= "    {\n";
            $code .= "        parent::__construct(\$ops);\n";
            $code .= "    }\n";
            $code .= "}\n";

            $bytesWritten = file_put_contents($filePath, $code);
            if ($bytesWritten === false) {
                $lastError = error_get_last();
                $message = $lastError['message'] ?? 'Unknown filesystem error';
                throw new RuntimeException("Cannot write generated class file: {$filePath}. {$message}");
            }

            $generatedFiles[] = $filePath;
        }

        if (empty($generatedFiles)) {
            throw new RuntimeException(
                "No class files were generated from XSD: {$xsdPath}. " .
                    "Check that the schema defines elements and that overwrite is enabled when files already exist."
            );
        }

        return $generatedFiles;
    }

    public function toXmlDocument(): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        $xml = $this->toXmlString();

        $previousUseInternalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();
        if ($xml === '' || !$dom->loadXML($xml)) {
            $parseErrors = self::formatLibxmlErrors(libxml_get_errors());
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseInternalErrors);
            throw new RuntimeException('Cannot generate DOMDocument from XML string: ' . $parseErrors);
        }
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);

        return $dom;
    }

    public function validateWithXsd(string $xsdPath, array &$errors = []): bool
    {
        if (!is_file($xsdPath)) {
            throw new InvalidArgumentException("XSD file not found: {$xsdPath}");
        }

        $previousUseInternalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $dom = $this->toXmlDocument();
            $isValid = $dom->schemaValidate($xsdPath);

            $errors = [];
            foreach (libxml_get_errors() as $error) {
                $errors[] = trim($error->message);
            }
            libxml_clear_errors();

            return $isValid;
        } finally {
            libxml_use_internal_errors($previousUseInternalErrors);
        }
    }

    public function __toString(): string
    {
        try {
            return $this->toXmlString();
        } catch (Throwable $e) {
            $this->lastSerializationError = $e->getMessage();
            $error = self::sanitizeForXmlComment($this->lastSerializationError);
            error_log('XMLS serialization error: ' . $this->lastSerializationError);

            return "<!-- XMLS serialization error: {$error} -->";
        }
    }

    public function deserialize(DOMElement $xml, array $namespaceTranslations = [], array $classTranslations = []): void
    {
        $this->tagName = $xml->tagName;
        $resolvedClassCache = [];
        $canDeserializeCache = [];

        // Set attributes
        foreach ($xml->attributes as $attribute) {
            $this->{$attribute->name} = $attribute->value;
        }

        // Process child nodes
        foreach ($xml->childNodes as $node) {
            if (!($node instanceof DOMElement) || empty($node->localName)) {
                continue;
            }

            if (isset($resolvedClassCache[$node->nodeName])) {
                $className = $resolvedClassCache[$node->nodeName];
            } else {
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
                $resolvedClassCache[$node->nodeName] = $className;
            }

            if (class_exists($className)) {
                $object = new $className();

                if (!isset($canDeserializeCache[$className])) {
                    $canDeserializeCache[$className] = method_exists($object, 'deserialize');
                }

                if ($canDeserializeCache[$className]) {
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
