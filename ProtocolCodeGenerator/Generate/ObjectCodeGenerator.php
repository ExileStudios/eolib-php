<?php

namespace ProtocolCodeGenerator\Generate;

use SimpleXMLElement;
use ProtocolCodeGenerator\Generate\SwitchCodeGenerator;
use ProtocolCodeGenerator\Generate\FieldCodeGeneratorBuilder;

/**
 * Represents the metadata for a field within an object, storing attributes such as field name, type, and whether it is part of an array.
 */
class FieldData {
    public $name;
    public $type;
    public $offset;
    public $array;

    /**
     * Initializes a new instance of FieldData with specified attributes.
     *
     * @param string $name The name of the field.
     * @param mixed $type The data type of the field.
     * @param int $offset The offset used for serialization/deserialization purposes.
     * @param bool $array Indicates whether the field is treated as part of an array.
     */
    public function __construct($name, $type, $offset, $array) {
        $this->name = $name;
        $this->type = $type;
        $this->offset = $offset;
        $this->array = $array;
    }
}

/**
 * Encapsulates the context in which an object's code generation occurs, holding state like whether chunked reading is enabled and tracking optional fields.
 */
class ObjectGenerationContext {
    public $chunkedReadingEnabled = false;
    public $reachedOptionalField = false;
    public $reachedDummy = false;
    public $needsOldWriterLengthVariable = false;
    public $accessibleFields = [];
    public $lengthFieldIsReferencedMap = [];
}

/**
 * Contains all the necessary structures and information needed during the generation of an object, including fields, methods, and serialization logic.
 */
class ObjectGenerationData {
    public $className;
    public $superInterfaces = [];
    public $fields;
    public $methods;
    public $serialize;
    public $deserialize;
    public $auxiliaryTypes;
    public $docstring;
    public $reprFields = ['byte_size'];

    /**
     * Constructs the container for object generation data.
     *
     * @param string $className The name of the class for which code is being generated.
     */
    public function __construct($className) {
        $this->className = $className;
        $this->fields = new CodeBlock();
        $this->methods = new CodeBlock();
        $this->serialize = new CodeBlock();
        $this->deserialize = new CodeBlock();
        $this->auxiliaryTypes = new CodeBlock();
        $this->docstring = new CodeBlock();
    }

    /**
     * Adds a method to the class being generated.
     *
     * @param CodeBlock $method The method code to add.
     */
    public function addMethod($method) {
        if (!empty($this->methods->lines)) {
            $this->methods->addLine();
        }
        $this->methods->addCodeBlock($method);
    }

    /**
     * Adds auxiliary type definitions to the class, useful for enums or custom types used within the class.
     *
     * @param CodeBlock $type The auxiliary type definition to add.
     */
    public function addAuxiliaryType($type) {
        $this->auxiliaryTypes->addCodeBlock($type);
    }

    /**
     * Gets the auxiliary type definitions that have been added to the class.
     * 
     * @return array The auxiliary type definitions.
     */
    public function getAuxiliaryType($type) {
        return $this->auxiliaryTypes->getLines();
    }
}

/**
 * Coordinates the generation of PHP classes based on XML definitions, handling all aspects of structure, serialization, and deserialization.
 */
class ObjectCodeGenerator {
    private $className;
    private $typeFactory;
    private $context;
    private $data;

    /**
     * Initializes a new instance of the ObjectCodeGenerator class with specified class name and type factory.
     *
     * @param string $className The name of the class to generate.
     * @param mixed $typeFactory The factory used for creating type instances.
     * @param ObjectGenerationContext|null $context The generation context, if any, or null to create a new context.
     */
    public function __construct($className, $typeFactory, $context = null) {
        $this->className = $className;
        $this->typeFactory = $typeFactory;
        $this->context = $context ?: new ObjectGenerationContext();
        $this->data = new ObjectGenerationData($className);
    }

    /**
     * Interprets and generates code based on a given XML instruction.
     *
     * @param SimpleXMLElement $instruction The XML element that specifies what code to generate.
     * @throws Exception If an unsupported or unknown instruction is encountered.
     */
    public function generateInstruction(SimpleXMLElement $instruction) {
        if ($this->context->reachedDummy) {
            throw new \Exception("<dummy> elements must not be followed by any other elements.");
        }

        $instructionName = $instruction->getName();
        
        switch ($instructionName) {
            case 'field':
                $this->generateField($instruction);
                break;
            case 'array':
                $this->generateArray($instruction);
                break;
            case 'length':
                $this->generateLength($instruction);
                break;
            case 'dummy':
                $this->generateDummy($instruction);
                break;
            case 'switch':
                $this->generateSwitch($instruction);
                break;
            case 'chunked':
                $this->generateChunked($instruction);
                break;
            case 'break':
                $this->generateBreak();
                break;
            default:
                throw new \Exception("Unknown instruction type: " . $instructionName);
        }
    }

    /**
     * Retrieves the data object which accumulates the generated code structures for the class.
     *
     * @return ObjectGenerationData The accumulated generation data.
     */
    public function data() {
        return $this->data;
    }

    /**
     * Compiles and returns the complete code for the class being generated, incorporating all elements like fields, methods, and type definitions.
     *
     * @return CodeBlock The complete class code.
     */
    public function code() {
        $simpleName = $this->className;
        if (strpos($simpleName, '.') !== false) {
            $simpleName = explode('.', $simpleName)[1];
        }

        $superInterfaces = "";
        if (!empty($this->data->superInterfaces)) {
            $superInterfaces = " implements " . implode(', ', $this->data->superInterfaces);
        }

        $result = new CodeBlock();
        $result->addImport("EoWriter", "Eolib\\Data");
        $result->addImport("EoReader", "Eolib\\Data");
        if (!empty($this->data->docstring)) {
            $result->addCodeBlock($this->data->docstring);

        $result->addLine("class {$simpleName}{$superInterfaces}")
            ->addLine("{")
            ->indent();
        }
        $result->addLine("private int \$byteSize = 0;")
            ->addCodeBlock($this->data->fields)
            ->addCodeBlock($this->generateGetByteSize())
            ->addCodeBlock($this->generateSetByteSize())
            ->addCodeBlock($this->data->methods)
            ->addCodeBlock($this->generateSerializeMethod())
            ->addCodeBlock($this->generateDeserializeMethod())
            ->addCodeBlock($this->generateToStringMethod());

        if (!empty($this->data->auxiliaryTypes)) {
            $result->unindent();
            $result->addLine("}");
            $result->addLine("");
            $result->addCodeBlock($this->data->auxiliaryTypes);
        } else {
            $result->unindent();
            $result->addLine("}");
        }

        return $result;
    }

    /**
     * Generates the method that returns the size in bytes of the data that was serialized or deserialized.
     *
     * @return CodeBlock The generated method code.
     */
    public function generateGetByteSize() {
        return (
            (new CodeBlock())
            ->addLine('/**')
            ->addLine(' * Returns the size of the data that this was deserialized from.')
            ->addLine(' *')
            ->addLine(' * @return int The size of the data that this was deserialized from.')
            ->addLine(' */')
            ->addLine('public function getByteSize(): int {')
            ->indent()
            ->addLine('return $this->byteSize;')
            ->unindent()
            ->addLine('}')
        );
    }

    /**
     * Generates the method that sets the size in bytes of the data that was serialized or deserialized.
     *
     * @return CodeBlock The generated method code.
     */
    public function generateSetByteSize() {
        return (
            (new CodeBlock())
            ->addLine('/**')
            ->addLine(' * Sets the size of the data that this was deserialized from.')
            ->addLine(' *')
            ->addLine(' * @param int $byteSize The size of the data that this was deserialized from.')
            ->addLine(' */')
            ->addLine('public function setByteSize(int $byteSize): void {')
            ->indent()
            ->addLine('$this->byteSize = $byteSize;')
            ->unindent()
            ->addLine('}')
        );
    }

    /**
     * Generates the method responsible for serializing the class instance into a writer object.
     *
     * @return CodeBlock The generated method code.
     */
    public function generateSerializeMethod() {
        $result = (
            (new CodeBlock())
            ->addLine("/**")
            ->addLine(" * Serializes an instance of `{$this->className}` to the provided `EoWriter`.")
            ->addLine(" *")
            ->addLine(" * @param EoWriter \$writer The writer that the data will be serialized to.")
            ->addLine(" * @param {$this->className} \$data The data to serialize.")
            ->addLine(" */")
            ->addLine("public static function serialize(EoWriter \$writer, {$this->className} \$data): void {")
            ->indent()
        );

        if ($this->context->needsOldWriterLengthVariable) {
            $result->addLine('$old_writer_length = $writer->getLength();');
        }

        $result->addCodeBlock($this->data->serialize);
        $result->unindent();
        $result->addLine('}');

        return $result;
    }

    /**
     * Generates the method responsible for deserializing data from a reader object into an instance of the class.
     *
     * @return CodeBlock The generated method code.
     */
    public function generateDeserializeMethod() {
        return (
            (new CodeBlock())
            ->addLine("/**")
            ->addLine(" * Deserializes an instance of `{$this->className}` from the provided `EoReader`.")
            ->addLine(" *")
            ->addLine(" * @param EoReader \$reader The reader that the data will be deserialized from.")
            ->addLine(" * @return {$this->className} The deserialized data.")
            ->addLine(" */")
            ->addLine("public static function deserialize(EoReader \$reader): {$this->className}")
            ->addLine("{")
            ->indent()
            ->addLine("\$data = new {$this->className}();")
            ->addLine('$old_chunked_reading_mode = $reader->isChunkedReadingMode();')
            ->addLine('try {')
            ->indent()
            ->addLine('$reader_start_position = $reader->getPosition();')
            ->addCodeBlock($this->data->deserialize)
            ->addLine("\$data->setByteSize(\$reader->getPosition() - \$reader_start_position);")
            ->addLine("")
            ->addLine("return \$data;")
            ->unindent()
            ->addLine('} finally {')
            ->indent()
            ->addLine('$reader->setChunkedReadingMode($old_chunked_reading_mode);')
            ->unindent()
            ->addLine('}')
            ->unindent()
            ->addLine('}')
        );
    }

    /**
     * Generates a method that provides a string representation of the object instance, primarily for debugging purposes.
     *
     * @return CodeBlock The generated method code.
     */
    public function generateToStringMethod() {
        $fields = $this->data->reprFields;
        $parts = [];
    
        foreach ($fields as $field) {
            $camelField = snakeCaseToCamelCase($field);
            $value = "\$this->{$camelField}";
    
            // Check if the field is an array based on the field definition
            $fieldLines = $this->data->fields->getLines();
            $isArray = false;
            $isObject = false;
            foreach ($fieldLines as $line) {
                if (strpos($line, $field) === false) {
                    continue;
                }

                // Escaping special regex characters in $camelField
                $escapedCamelField = preg_quote($camelField, '/');
                $pattern = "/(private|public)\s+(\??[\w\\]+)\s+\$" . $escapedCamelField . "(\s*=\s*[^;]+)?\s*;/";
            
                // Regex to match property declarations including nullable types
                if (preg_match($pattern, $line, $matches)) {
                    $type = $matches[1];
            
                    // Check if the type is an array (this assumes arrays are declared with explicit array type hints like `array` or `[]`)
                    if (strpos($type, 'array') !== false) {
                        $isArray = true;
                        break;
                    }
            
                    // Check if the type is an object by excluding primitive types
                    if (!in_array($type, ['int', 'string', 'float', 'bool', '?int', '?string', '?float', '?bool'])) {
                        $isObject = true;
                        break;
                    }
                }
            }            
    
            $part = "{$camelField}=";

            if ($isObject) {
                $part .= "\".var_export({$value}, true).\"";
            } else {
                if ($isArray) {
                    $value = "[\" . implode(', ', array_map(function (\$item) {
                        return var_export(\$item, true); // Use var_export for each item in the array
                    }, \$this->{$camelField})) . \"]";
                }

                $part .= "{$value}";
            }

            $parts[] = $part;
        }
    
        $reprStr = implode(", ", $parts);
    
        $codeBlock = new CodeBlock();
        $codeBlock->addLine("/**")
            ->addLine(" * Returns a string representation of the object.")
            ->addLine(" *")
            ->addLine(" * @return string")
            ->addLine(" */")
            ->addLine("public function __toString(): string {")
            ->indent()
            ->addLine("return \"{$this->className}({$reprStr})\";")
            ->unindent()
            ->addLine("}");
    
        return $codeBlock;
    }
    
    /**
     * Generates code for a field based on provided XML attributes, setting up serialization and deserialization logic.
     *
     * @param SimpleXMLElement $protocolField The XML element containing field attributes.
     */
    public function generateField($protocolField) {
        $optional = $protocolField['optional'];
        $this->checkOptionalField($optional);
        $fieldCodeGenerator = (
            $this->fieldCodeGeneratorBuilder()
            ->name($protocolField['name'])
            ->type($protocolField['type'])
            ->length($protocolField['length'])
            ->padded($protocolField['padded'])
            ->optional($optional)
            ->hardcodedValue($protocolField[0])
            ->comment($protocolField['comment'])
            ->build()
        );

        $fieldCodeGenerator->generateField();
        $fieldCodeGenerator->generateSerialize();
        $fieldCodeGenerator->generateDeserialize();

        if ($optional) {
            $this->context->reachedOptionalField = true;
        }
    }

    /**
     * Generates code for an array field including handling of optional and delimited properties.
     *
     * @param SimpleXMLElement $protocolArray The XML element that specifies the array field configuration.
     * @throws Exception If delimited arrays are attempted without chunked reading being enabled.
     */
    public function generateArray($protocolArray) {
        $optional = $protocolArray['optional'];
        $this->checkOptionalField($optional);

        $delimited = $protocolArray['delimited'];
        if ($delimited && !$this->context->chunkedReadingEnabled) {
            throw new \Exception("Cannot generate a delimited array instruction unless chunked reading is enabled.");
        }

        $fieldCodeGenerator = (
            $this->fieldCodeGeneratorBuilder()
            ->name($protocolArray['name'])
            ->type($protocolArray['type'])
            ->length($protocolArray['length'])
            ->optional($optional)
            ->comment($protocolArray['comment'])
            ->arrayField(true)
            ->delimited($delimited)
            ->trailingDelimiter($protocolArray['trailing-delimiter'])
            ->build()
        );

        $fieldCodeGenerator->generateField();
        $fieldCodeGenerator->generateSerialize();
        $fieldCodeGenerator->generateDeserialize();

        if ($optional) {
            $this->context->reachedOptionalField = true;
        }
    }

    /**
     * Generates code for a length field which is used to specify the size of other fields or arrays in serialization.
     *
     * @param SimpleXMLElement $protocolLength The XML element defining the length field.
     */
    public function generateLength($protocolLength) {
        $optional = $protocolLength['optional'];
        $this->checkOptionalField($optional);

        $fieldCodeGenerator = (
            $this->fieldCodeGeneratorBuilder()
            ->name($protocolLength['name'])
            ->type($protocolLength['type'])
            ->offset($protocolLength['offset'])
            ->lengthField(true)
            ->optional($optional)
            ->comment($protocolLength['comment'])
            ->build()
        );

        $fieldCodeGenerator->generateField();
        $fieldCodeGenerator->generateSerialize();
        $fieldCodeGenerator->generateDeserialize();

        if ($optional) {
            $this->context->reachedOptionalField = true;
        }
    }

    /**
     * Generates serialization and deserialization logic for a dummy field, typically used for padding or alignment.
     *
     * @param SimpleXMLElement $protocolDummy The XML element containing the dummy field configuration.
     */
    public function generateDummy($protocolDummy) {
        $fieldCodeGenerator = (
            $this->fieldCodeGeneratorBuilder()
            ->type($protocolDummy['type'])
            ->hardcodedValue($protocolDummy[0])
            ->comment($protocolDummy['comment'])
            ->build()
        );

        $needsIfGuards = empty($this->data->serialize->lines) && empty($this->data->deserialize->lines);

        if ($needsIfGuards) {
            $this->data->serialize->beginControlFlow("if (\$writer->getLength() === \$old_writer_length)");
            $this->data->deserialize->beginControlFlow("if (\$reader->getPosition() === \$reader_start_position)");
        }

        $fieldCodeGenerator->generateSerialize();
        $fieldCodeGenerator->generateDeserialize();

        if ($needsIfGuards) {
            $this->data->serialize->endControlFlow();
            $this->data->deserialize->endControlFlow();
        }

        $this->context->reachedDummy = true;

        if ($needsIfGuards) {
            $this->context->needsOldWriterLengthVariable = true;
        }
    }

    /**
     * Provides a builder to configure and return a FieldCodeGenerator instance based on the current type factory and context.
     *
     * @return FieldCodeGeneratorBuilder A builder to create a FieldCodeGenerator.
     */
    public function fieldCodeGeneratorBuilder() {
        return new FieldCodeGeneratorBuilder($this->typeFactory, $this->context, $this->data);
    }

    /**
     * Validates the sequence of optional fields, ensuring that non-optional fields do not follow optional ones.
     *
     * @param bool $optional Indicates if the current field is optional.
     * @throws RuntimeException If a non-optional field follows an optional field in the definition.
     */
    public function checkOptionalField($optional) {
        if ($this->context->reachedOptionalField && !$optional) {
            throw new \RuntimeException("Optional fields may not be followed by non-optional fields.");
        }
    }

    /**
     * Generates switch-case logic for handling multiple types or variations of fields based on a specified field's value.
     *
     * @param SimpleXMLElement $protocolSwitch The XML element defining the switch logic.
     */
    public function generateSwitch($protocolSwitch) {
        $switchCodeGenerator = new SwitchCodeGenerator(
            !empty($protocolSwitch['field']) ? (string)$protocolSwitch['field'][0] : null,
            $this->typeFactory,
            $this->context,
            $this->data
        );

        $protocol_cases = $protocolSwitch->xpath("case");

        if (empty($protocol_cases)) {
            return;
        }

        $switchCodeGenerator->generateCaseDataInterface($protocol_cases);
        $switchCodeGenerator->generateCaseDataField();

        $reachedOptionalField = $this->context->reachedOptionalField;
        $reachedDummy = $this->context->reachedDummy;
        $start = true;

        foreach ($protocol_cases as $protocol_case) {
            $casecontext = $switchCodeGenerator->generateCase($protocol_case, $start);

            $reachedOptionalField = $reachedOptionalField || $casecontext->reachedOptionalField;
            $reachedDummy = $reachedDummy || $casecontext->reachedDummy;
            $start = false;
        }

        $this->context->reachedOptionalField = $reachedOptionalField;
        $this->context->reachedDummy = $reachedDummy;
    }

    /**
     * Enables chunked reading mode and processes a set of instructions designed to operate within that mode.
     *
     * @param SimpleXMLElement $protocolChunked The XML element containing chunked reading instructions.
     */
    public function generateChunked($protocolChunked) {
        $wasAlreadyEnabled = $this->context->chunkedReadingEnabled;
        if (!$wasAlreadyEnabled) {
            $this->context->chunkedReadingEnabled = true;
            $this->data->deserialize->addLine("\$reader->setChunkedReadingMode(true);");
        }

        foreach ($protocolChunked->children() as $instruction) {
            $this->generateInstruction($instruction);
        }

        if (!$wasAlreadyEnabled) {
            $this->context->chunkedReadingEnabled = false;
            $this->data->deserialize->addLine("\$reader->setChunkedReadingMode(false);");
        }
    }

    /**
     * Inserts a break in serialization/deserialization processes, typically used within chunked reading contexts.
     *
     * @throws RuntimeException If attempted outside of chunked reading mode.
     */
    public function generateBreak() {
        if (!$this->context->chunkedReadingEnabled) {
            throw new \RuntimeException("Cannot generate a break instruction unless chunked reading is enabled.");
        }

        $this->context->reachedOptionalField = false;
        $this->context->reachedDummy = false;

        $this->data->serialize->addLine("\$writer->addByte(0xFF);");
        $this->data->deserialize->addLine("\$reader->nextChunk();");
    }
}

