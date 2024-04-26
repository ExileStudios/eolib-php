<?php

namespace ProtocolCodeGenerator\Generate;

use InvalidArgumentException;
use ProtocolCodeGenerator\Type\Length;
use ProtocolCodeGenerator\Type\BlobType;
use ProtocolCodeGenerator\Type\BoolType;
use ProtocolCodeGenerator\Type\EnumType;
use ProtocolCodeGenerator\Type\BasicType;
use ProtocolCodeGenerator\Type\CustomType;
use ProtocolCodeGenerator\Type\StringType;
use ProtocolCodeGenerator\Type\StructType;
use ProtocolCodeGenerator\Type\IntegerType;
use ProtocolCodeGenerator\Util\NumberUtils;
use ProtocolCodeGenerator\Util\DocstringUtils;
use ProtocolCodeGenerator\Type\HasUnderlyingType;

/**
 * Generates field-specific code for serialization and deserialization processes,
 * managing type conversions, optional and array fields, and handling deprecated fields.
 */
class FieldCodeGenerator
{
    private $typeFactory;
    private $context;
    private $data;
    private $name;
    private $typeString;
    private $lengthString;
    private $padded;
    private $optional;
    private $hardcodedValue;
    private $comment;
    private $arrayField;
    private $delimited;
    private $trailingDelimiter;
    private $lengthField;
    private $offset;

    /**
     * Constructs a FieldCodeGenerator instance with necessary context and configuration for field generation.
     *
     * @param TypeFactory $typeFactory Factory for creating type instances based on type strings.
     * @param Context $context The current generation context including accessible fields and type information.
     * @param Data $data Container for accumulated code and metadata throughout the generation process.
     * @param string $name The name of the field, converted to camel case.
     * @param string $typeString The type identifier string for the field.
     * @param string $lengthString The string representing the length of the field, if applicable.
     * @param bool $padded Indicates whether the field is padded.
     * @param bool $optional Indicates whether the field is optional.
     * @param mixed $hardcodedValue A predetermined value for the field, if any.
     * @param string $comment A descriptive comment for the field.
     * @param bool $arrayField Indicates whether the field is treated as an array.
     * @param bool $delimited Indicates whether the array field uses delimiters.
     * @param bool $trailingDelimiter Indicates if there's a trailing delimiter after the last element in a delimited array.
     * @param string $lengthField The name of the field that specifies the length of this field, if any.
     * @param int $offset An optional offset applied to numeric values of the field during serialization/deserialization.
     */
    public function __construct(
        $typeFactory,
        $context,
        $data,
        $name,
        $typeString,
        $lengthString,
        $padded,
        $optional,
        $hardcodedValue,
        $comment,
        $arrayField,
        $delimited,
        $trailingDelimiter,
        $lengthField,
        $offset
    ) {
        $this->typeFactory = $typeFactory;
        $this->context = $context;
        $this->data = $data;
        $this->name = snakeCaseToCamelCase($name);
        $this->typeString = $typeString;
        $this->lengthString = snakeCaseToCamelCase($lengthString);
        $this->padded = $padded;
        $this->optional = $optional;
        $this->hardcodedValue = $hardcodedValue;
        $this->comment = $comment;
        $this->arrayField = $arrayField;
        $this->delimited = $delimited;
        $this->trailingDelimiter = $trailingDelimiter;
        $this->lengthField = snakeCaseToCamelCase($lengthField);
        $this->offset = $offset;
        $this->validate();
    }

    /**
     * Performs a comprehensive validation of the field configuration to ensure consistency and correctness
     * before generating code.
     */
    private function validate()
    {
        $this->validateSpecialFields();
        $this->validateOptionalField();
        $this->validateArrayField();
        $this->validateLengthField();
        $this->validateUnnamedField();
        $this->validateHardcodedValue();
        $this->validateUniqueName();
        $this->validateLengthAttribute();
    }

    /**
     * Validates the compatibility of special field attributes like array and length fields,
     * ensuring they do not conflict.
     */
    private function validateSpecialFields()
    {
        if ($this->arrayField && $this->lengthField) {
            throw new \RuntimeException("A field cannot be both a length field and an array field.");
        }
    }

    /**
     * Ensures optional fields have a specified name.
     */
    private function validateOptionalField()
    {
        if (!$this->optional) {
            return;
        }

        if ($this->name === null) {
            throw new \RuntimeException("Optional fields must specify a name.");
        }
    }

    /**
     * Checks array field configurations for consistency and correct settings,
     * including handling of delimiters and bounded types.
     */
    private function validateArrayField()
    {
        if ($this->arrayField) {
            if ($this->name === null) {
                throw new \RuntimeException("Array fields must specify a name.");
            }
            if ($this->hardcodedValue) {
                throw new \RuntimeException("Array fields may not specify hardcoded values.");
            }
            if (!$this->delimited && !$this->getType()->bounded()) {
                throw new \RuntimeException(
                    "Unbounded element type ({$this->typeString}) "
                    . "forbidden in non-delimited array."
                );
            }
        } else {
            if ($this->delimited) {
                throw new \RuntimeException("Only arrays can be delimited.");
            }
        }

        if (!$this->delimited && $this->trailingDelimiter) {
            throw new \RuntimeException("Only delimited arrays can have a trailing delimiter.");
        }
    }

    /**
     * Ensures length fields are configured correctly, including checks for proper naming,
     * type restrictions, and absence of hardcoded values.
     */
    private function validateLengthField()
    {
        if ($this->lengthField) {
            if ($this->name === null) {
                throw new \RuntimeException("Length fields must specify a name.");
            }
            if ($this->hardcodedValue !== null) {
                throw new \RuntimeException("Length fields may not specify hardcoded values.");
            }
            $fieldType = $this->getType();
            if (!($fieldType instanceof IntegerType)) {
                throw new \RuntimeException(
                    "{$fieldType->name} is not a numeric type, "
                    . "so it is not allowed for a length field."
                );
            }
        } else {
            if ($this->offset != 0) {
                throw new \RuntimeException("Only length fields can have an offset.");
            }
        }
    }

    /**
     * Validates unnamed fields, ensuring they have hardcoded values and are not marked as optional.
     */
    private function validateUnnamedField()
    {
        if ($this->name !== null) {
            return;
        }

        if ($this->hardcodedValue === null) {
            throw new \RuntimeException("Unnamed fields must specify a hardcoded field value.");
        }

        if ($this->optional) {
            throw new \RuntimeException("Unnamed fields may not be optional.");
        }
    }

    /**
     * Checks the validity of hardcoded values against their field types,
     * ensuring they conform to expected length and type requirements.
     */
    private function validateHardcodedValue()
    {
        if ($this->hardcodedValue === null || empty(trim($this->hardcodedValue))) {
            return;
        }

        $fieldType = $this->getType();

        if ($fieldType instanceof StringType) {
            $length = tryCastInt($this->lengthString);
            if ($length !== null && $length != strlen($this->hardcodedValue)) {
                throw new \RuntimeException(
                    "Expected length of {$length} for hardcoded string value "
                    . "'{$this->hardcodedValue}'."
                );
            }
        }

        if (!($fieldType instanceof BasicType)) {
            throw new \RuntimeException(
                "Hardcoded field values are not allowed for {$fieldType->name()} fields "
                . "(must be a basic type)."
            );
        }
    }
    
    /**
     * Ensures that each field name within a context is unique to prevent redefinitions.
     */
    private function validateUniqueName()
    {
        if ($this->name === null) {
            return;
        }

        if (isset($this->context->accessibleFields[$this->name])) {
            throw new \RuntimeException("Cannot redefine {$this->name} field.");
        }
    }

    /**
     * Validates the correctness of the length attribute for fields, ensuring it references an existing length field
     * or is a valid numeric literal.
     */
    private function validateLengthAttribute()
    {
        if ($this->lengthString === null) {
            return;
        }

        if (
            !ctype_digit($this->lengthString)
            && !isset($this->context->lengthFieldIsReferencedMap[$this->lengthString])
        ) {
            throw new \RuntimeException(
                "Length attribute \"{$this->lengthString}\" must be a numeric literal, "
                . "or refer to a length field."
            );
        }

        $isAlreadyReferenced = $this->context->lengthFieldIsReferencedMap[$this->lengthString] ?? false;

        if ($isAlreadyReferenced) {
            throw new \RuntimeException(
                "Length field \"{$this->lengthString}\" must not be referenced by multiple fields."
            );
        }
    }

    /**
     * Retrieves the PHP type name for a given field type, handling different data types
     * including custom types.
     *
     * @param Type $fieldType The field type for which the PHP type name is required.
     * @return string The PHP type name corresponding to the field type.
     */
    public function getPHPTypeName($fieldType)
    {
        if ($fieldType instanceof IntegerType) {
            return "int";
        }

        if ($fieldType instanceof StringType) {
            return "string";
        }

        if ($fieldType instanceof BoolType) {
            return "bool";
        }

        if ($fieldType instanceof BlobType) {
            return "string";
        }

        if ($fieldType instanceof CustomType) {
            return $fieldType->name();
        }

        throw new \AssertionError("Unhandled Type");
    }

    /**
     * Generates PHP code that defines and initializes a class field based on its type and configuration.
     * It also handles deprecated fields and generates accessor methods.
     */
    public function generateField()
    {
        if ($this->name === null) {
            return;
        }

        $fieldType = $this->getType();

        $typeName = $this->getPHPTypeName($fieldType);
        if ($this->arrayField) {
            $typeName = "array";
        }

        if ($this->optional) {
            $typeName = "?{$typeName}";
        }

        $this->hardcodedValue = trim($this->hardcodedValue);
        if ($this->hardcodedValue === null) {
            $initializer = null;
        } elseif ($fieldType instanceof StringType) {
            $initializer = "\"{$this->hardcodedValue}\"";
        } else {
            $initializer = $this->hardcodedValue;
        }

        $this->context->accessibleFields[$this->name] = new FieldData(
            $this->name,
            $fieldType,
            $this->offset,
            $this->arrayField
        );

        $this->data->fields->addLine(
            "private {$typeName} \${$this->name}" . (!empty($initializer) ? " = {$initializer};" : ";")
        );

        if ($fieldType instanceof CustomType) {
            $this->data->fields->addImportByType($fieldType);
        }

        if ($this->lengthField) {
            $this->context->lengthFieldIsReferencedMap[$this->name] = false;
            return;
        }

        $docstring = $this->generateAccessorDocstring();

        $getterName = "get" . ucfirst($this->name);
        $getter = new CodeBlock();
        if (!empty($docstring->lines)) {
            $getter->addCodeBlock($docstring);
        }
        $getter->addLine("public function {$getterName}(): {$typeName}")
            ->addLine("{")
            ->indent()
            ->addLine("return \$this->{$this->name};")
            ->unindent()
            ->addLine("}");

        $this->data->addMethod($getter);

        $this->data->reprFields[] = $this->name;
        if ($this->hardcodedValue === null || empty($this->hardcodedValue)) {
            $setterName = "set" . ucfirst($this->name);
            $setter = new CodeBlock();
            if (!empty($docstring->lines)) {
                $setter->addCodeBlock($docstring);
            }
            $setter->addLine("public function {$setterName}({$typeName} \${$this->name}): void")
                ->addLine("{")
                ->indent()
                ->addLine("\$this->{$this->name} = \${$this->name};");

            if (isset($this->context->lengthFieldIsReferencedMap[$this->lengthString])) {
                $this->context->lengthFieldIsReferencedMap[$this->lengthString] = true;
                $lengthFieldData = $this->context->accessibleFields[$this->lengthString];
                $setter->addLine("\$this->{$lengthFieldData->name} = strlen(\$this->{$this->name});");
            }

            $setter->unindent()->addLine("}");
            $this->data->addMethod($setter);
        }

        $deprecated = DeprecatedFields::getDeprecatedField($this->data->className, $this->name);
        if ($deprecated !== null) {
            $oldName = $deprecated->oldFieldName;
            $deprecatedDocstring = (new CodeBlock())
                ->addLine("/**")
                ->addLine(" * @deprecated Use `{$this->name}` instead. (Deprecated since v{$deprecated->since})")
                ->addLine(" */");
            $deprecationWarning = "{$this->data->className}::{$deprecated->oldFieldName} is deprecated as of v{$deprecated->since}, use {$this->name} instead.";
            $this->data->addMethod(
                (new CodeBlock())
                ->addLine("public function {$oldName}(): {$typeName}")
                ->addLine("{")
                ->indent()
                ->addCodeBlock($deprecatedDocstring)
                ->addLine("trigger_error('{$deprecationWarning}', E_USER_DEPRECATED);")
                ->addLine("return \$this->{$this->name};")
                ->unindent()
                ->addLine("}")
            );
            if ($this->hardcodedValue === null) {
                $this->data->addMethod(
                    (new CodeBlock())
                    ->addLine("public function set{$oldName}({$typeName} ${$this->name}): void")
                    ->indent()
                    ->addCodeBlock($deprecatedDocstring)
                    ->addLine("$this->{$this->name} = ${$this->name};")
                    ->unindent()
                );
            }
        }
    }

    /**
     * Generates the serialization logic for fields, handling optional fields,
     * arrays, and basic type serialization.
     */
    public function generateSerialize()
    {
        $this->generateSerializeMissingOptionalGuard();
        $this->generateSerializeNoneNotAllowedError();
        $this->generateSerializeLengthCheck();

        if ($this->arrayField) {
            $arraySizeExpression = $this->getLengthExpression();
            if ($arraySizeExpression === null) {
                $arraySizeExpression = "count(\$data->{$this->name})";
            }

            $this->data->serialize->beginControlFlow("for (\$i = 0; \$i < {$arraySizeExpression}; \$i++)");

            if ($this->delimited && !$this->trailingDelimiter) {
                $this->data->serialize->beginControlFlow("if (\$i > 0)");
                $this->data->serialize->addLine("\$writer->addByte(0xFF);");
                $this->data->serialize->endControlFlow();
            }
        }

        $this->data->serialize->addCodeBlock($this->getWriteStatement());

        if ($this->arrayField) {
            if ($this->delimited && $this->trailingDelimiter) {
                $this->data->serialize->addLine("\$writer->addByte(0xFF);");
            }
            $this->data->serialize->endControlFlow();
        }

        if ($this->optional) {
            $this->data->serialize->endControlFlow();
        }
    }

    /**
     * Generates the deserialization logic for fields, handling optional fields,
     * arrays, and basic type deserialization.
     */
    public function generateDeserialize()
    {
        if ($this->optional) {
            $this->data->deserialize->beginControlFlow("if (\$reader->remaining() > 0)");
        }

        if ($this->arrayField) {
            $this->generateDeserializeArray();
        } else {
            $this->data->deserialize->addCodeBlock($this->getReadStatement());
        }

        if ($this->optional) {
            $this->data->deserialize->endControlFlow();
        }
    }

    /**
     * Generates a docstring for field accessors, detailing conditions like length and value range
     * based on field configuration.
     *
     * @return CodeBlock The generated docstring code block.
     */
    public function generateAccessorDocstring()
    {
        $notes = [];

        if ($this->lengthString !== null) {
            $sizeDescription = "";
            $fieldData = isset($this->context->accessibleFields[$this->lengthString]) ? $this->context->accessibleFields[$this->lengthString] : null;
            if ($fieldData) {
                $maxValue = getMaxValueOf($fieldData->type) + $fieldData->offset;
                $sizeDescription = "{$maxValue} or less";
            } else {
                $sizeDescription = "`{$this->lengthString}`";
                if ($this->padded) {
                    $sizeDescription .= " or less";
                }
            }
            $notes[] = "Length must be {$sizeDescription}.";
        }

        $fieldType = $this->getType();
        if ($fieldType instanceof IntegerType) {
            $valueDescription = $this->arrayField ? "Element value" : "Value";
            $notes[] = "{$valueDescription} range is 0-" . getMaxValueOf($fieldType) . ".";
        }

        return generateDocstring($this->comment, $notes);
    }

    /**
     * Generates a guard clause for serialization to handle missing optional fields.
     */
    public function generateSerializeMissingOptionalGuard()
    {
        if (!$this->optional) {
            return;
        }

        if ($this->context->reachedOptionalField) {
            $this->data->serialize->addLine(
                "\$reachedMissingOptional = \$reachedMissingOptional || \$data->{$this->name} === null;"
            );
        } else {
            $this->data->serialize->addLine("\$reachedMissingOptional = \$data->{$this->name} === null;");
        }
        $this->data->serialize->beginControlFlow("if (!\$reachedMissingOptional)");
    }

    /**
     * Generates a serialization error check if a required field is null, which is not allowed.
     */
    public function generateSerializeNoneNotAllowedError()
    {
        if ($this->optional || $this->name === null || $this->hardcodedValue !== null && !empty($this->hardcodedValue)) {
            return;
        }

        $this->data->serialize->beginControlFlow("if (\$data->{$this->name} === null)");
        $this->data->serialize->addLine("throw new SerializationError('{$this->name} must be provided.');");
        $this->data->serialize->endControlFlow();
        $this->data->serialize->addImport("SerializationError", "Eolib\\Protocol");
    }

    /**
     * Checks the length of data being serialized against expected constraints, generating appropriate error messages.
     */
    public function generateSerializeLengthCheck()
    {
        if ($this->name === null) {
            return;
        }

        $lengthExpression = null;
        $fieldData = isset($this->context->accessibleFields[$this->lengthString]) ? $this->context->accessibleFields[$this->lengthString] : null;
        if ($fieldData) {
            $lengthExpression = getMaxValueOf($fieldData->type) + $fieldData->offset;
        } else {
            $lengthExpression = $this->lengthString;
        }
 
        if ($lengthExpression === null) {
            return;
        }
 
        $variableSize = $this->padded || $fieldData !== null;
        $lengthCheckOperator = $variableSize ? ">" : "!=";
        $expectedLengthDescription = $variableSize
            ? "{$lengthExpression} or less"
            : "exactly {$lengthExpression}";
        $errorMessage = "Expected length of "
            . $this->name
            . " to be "
            . $expectedLengthDescription
            . ", got {strlen(\$data->{$this->name})}.";
 
        $this->data->serialize->beginControlFlow(
            "if (strlen(\$data->{$this->name}) {$lengthCheckOperator} {$lengthExpression})"
        );
        $this->data->serialize->addLine("throw new SerializationError(\"{$errorMessage}\");");
        $this->data->serialize->endControlFlow();
        $this->data->serialize->addImport("SerializationError", "Eolib\\Protocol");
    }
    
    /**
     * Constructs a statement for writing data based on the field type and configuration.
     * Handles basic types, blobs, and structures, ensuring correct data handling during serialization.
     *
     * @return CodeBlock The generated code for writing the field value.
     */
    public function getWriteStatement()
    {
       $realType = $this->getType();
       $type = $realType;

       if ($type instanceof HasUnderlyingType) {
           $type = $type->underlyingType();
       }

       $valueExpression = $this->getWriteValueExpression();

       if ($realType instanceof BoolType) {
           $valueExpression = "{$valueExpression} ? 1 : 0";
       }

       if ($realType instanceof EnumType) {
           $valueExpression = "(int) {$valueExpression}";
       }

       $offsetExpression = $this->getLengthOffsetExpression(-$this->offset);
       if ($offsetExpression !== null) {
           $valueExpression .= $offsetExpression;
       }

       $result = new CodeBlock();

       if ($type instanceof BasicType) {
           $lengthExpression = $this->arrayField ? null : $this->getLengthExpression();
           $writeStatement = $this->getWriteStatementForBasicType(
               $type,
               $valueExpression,
               $lengthExpression,
               $this->padded
           );
           $result->addLine($writeStatement);
       } elseif ($type instanceof BlobType) {
           $result->addLine("\$writer->addBytes({$valueExpression});");
       } elseif ($type instanceof StructType) {
           $result->addLine("{$type->name()}::serialize(\$writer, {$valueExpression});");
           $result->addImportByType($type);
       } else {
           throw new \AssertionError("Unhandled Type");
       }

       if ($this->optional) {
           $result->addImport("cast", "typing");
       }

       return $result;
    }

    /**
     * Constructs the value expression to be written for a field, handling basic types, arrays, and hardcoded values.
     *
     * @return string The expression to write the field value.
     */
    public function getWriteValueExpression()
    {
        if ($this->name === null) {
            $type = $this->getType();
            if ($type instanceof IntegerType) {
                if (ctype_digit($this->hardcodedValue)) {
                    return $this->hardcodedValue;
                }
                throw new \RuntimeException("\"{$this->hardcodedValue}\" is not a valid integer value.");
            } elseif ($type instanceof BoolType) {
                if ($this->hardcodedValue === "false") {
                    return "0";
                } elseif ($this->hardcodedValue === "true") {
                    return "1";
                }
                throw new \RuntimeException("\"{$this->hardcodedValue}\" is not a valid bool value.");
            } elseif ($type instanceof StringType) {
                return "'{$this->hardcodedValue}'";
            } else {
                throw new \AssertionError("Unhandled BasicType");
            }
        } else {
            $fieldReference = "\$data->{$this->name}";
            if ($this->arrayField) {
                $fieldReference .= "[\$i]";
            }
            return $fieldReference;
        }
    }

    /**
     * Generates the specific write statement for basic types, applying any necessary padding or fixed-length constraints.
     *
     * @param Type $type The basic type of the field.
     * @param string $valueExpression The expression representing the value to write.
     * @param string|null $lengthExpression The expression representing the length for fixed-length fields.
     * @param bool $padded Whether the field should be padded.
     * @return string The PHP statement to write the field value.
     */
    public static function getWriteStatementForBasicType($type, $valueExpression, $lengthExpression, $padded)
    {
        $paddedStr = $padded ? 'true' : 'false';
        if ($type->name() === "byte") {
            return "\$writer->addByte({$valueExpression});";
        } elseif ($type->name() === "char") {
            return "\$writer->addChar({$valueExpression});";
        } elseif ($type->name() === "short") {
            return "\$writer->addShort({$valueExpression});";
        } elseif ($type->name() === "three") {
            return "\$writer->addThree({$valueExpression});";
        } elseif ($type->name() === "int") {
            return "\$writer->addInt({$valueExpression});";
        } elseif ($type->name() === "string") {
            if ($lengthExpression === null) {
                return "\$writer->addString({$valueExpression});";
            } else {
                return "\$writer->addFixedString({$valueExpression}, {$lengthExpression}, {$paddedStr});";
            }
        } elseif ($type->name() === "encoded_string") {
            if ($lengthExpression === null) {
                return "\$writer->addEncodedString({$valueExpression});";
            } else {
                return "\$writer->addFixedEncodedString({$valueExpression}, {$lengthExpression}, {$paddedStr});";
            }
        } else {
            throw new \AssertionError("Unhandled BasicType");
        }
    }

    /**
     * Generates the PHP code for deserializing an array field, handling both fixed-length and delimited arrays.
     */
    public function generateDeserializeArray()
    {
        $arrayLengthExpression = $this->getLengthExpression();

        if ($arrayLengthExpression === null && !$this->delimited) {
            $elementSize = $this->getType()->fixedSize();
            if ($elementSize !== null) {
                $arrayLengthVariableName = "\${$this->name}_length";
                $this->data->deserialize->addLine(
                    "{$arrayLengthVariableName} = (int) \$reader->remaining() / {$elementSize};"
                );
                $arrayLengthExpression = $arrayLengthVariableName;
            }
        }

        $this->data->deserialize->addLine("\$data->{$this->name} = [];");

        if ($arrayLengthExpression === null) {
            $this->data->deserialize->beginControlFlow("while (\$reader->remaining() > 0)");
        } else {
            $this->data->deserialize->beginControlFlow("for (\$i = 0; \$i < {$arrayLengthExpression}; \$i++)");
        }

        $this->data->deserialize->addCodeBlock($this->getReadStatement());

        if ($this->delimited) {
            $needsGuard = !$this->trailingDelimiter && $arrayLengthExpression !== null;
            if ($needsGuard) {
                $this->data->deserialize->beginControlFlow("if (\$i + 1 < {$arrayLengthExpression})");
            }
            $this->data->deserialize->addLine("\$reader->nextChunk();");
            if ($needsGuard) {
                $this->data->deserialize->endControlFlow();
            }
        }

        $this->data->deserialize->endControlFlow();
    }

    /**
     * Constructs a read statement for a field based on its type, handling basic types, blobs, and structures.
     * This method ensures data is correctly read and converted during deserialization.
     *
     * @return CodeBlock The generated code for reading the field value.
     */
    public function getReadStatement()
    {
        $realType = $this->getType();
        $type = $realType;

        if ($type instanceof HasUnderlyingType) {
            $type = $type->underlyingType();
        }

        $statement = new CodeBlock();

        if ($this->arrayField) {
            $statement->add("\$data->{$this->name}[] = ");
        } elseif ($this->name !== null) {
            $statement->add("\$data->{$this->name} = ");
        }

        if ($type instanceof BasicType) {
            $lengthExpression = $this->arrayField ? null : $this->getLengthExpression();
            $readBasicType = $this->getReadStatementForBasicType(
                $type,
                $lengthExpression,
                $this->padded
            );

            $offsetExpression = $this->getLengthOffsetExpression($this->offset);
            if ($offsetExpression !== null) {
                $readBasicType .= $offsetExpression;
            }

            if ($realType instanceof BoolType) {
                $statement->add("{$readBasicType} !== 0");
            } elseif ($realType instanceof EnumType) {
                $statement->add("{$realType->name()}({$readBasicType})");
            } else {
                $statement->add($readBasicType);
            }
        } elseif ($type instanceof BlobType) {
            $statement->add("\$reader->getBytes(\$reader->remaining())");
        } elseif ($type instanceof StructType) {
            $statement->add("{$type->name()}::deserialize(\$reader)")->addImportByType($type);
        } else {
            throw new \AssertionError("Unhandled Type");
        }

        return $statement->add(";");
    }

    /**
     * Determines the expression to use for a field's length, handling direct lengths and referenced length fields.
     *
     * @return string|null The length expression, or null if not applicable.
     */
    public function getLengthExpression()
    {
        if ($this->lengthString === null) {
            return null;
        }

        $expression = $this->lengthString;
        if (!ctype_digit($expression)) {
            $fieldData = isset($this->context->accessibleFields[$expression]) ? $this->context->accessibleFields[$expression] : null;
            if ($fieldData === null) {
                throw new \RuntimeException("Referenced {$expression} field is not accessible.");
            }
            $expression = "\$data->{$expression}";
        }

        return $expression;
    }

    /**
     * Computes an offset expression for length adjustments if necessary, providing support for complex field configurations.
     *
     * @param int $offset The numeric offset to apply.
     * @return string|null The offset expression, or null if no offset is needed.
     */
    public static function getLengthOffsetExpression($offset)
    {
        if (!is_numeric($offset)) {
            return null;
        }

        $offset = (int) $offset; // Cast $offset to an integer
        if ($offset !== 0) {
            return ($offset > 0 ? " + " : " - ") . abs($offset);
        }

        return null;
    }

    /**
     * Generates the read statement for basic types, incorporating any necessary adjustments for padding or fixed lengths.
     *
     * @param Type $type The basic type of the field.
     * @param string|null $lengthExpression The length expression for fixed-length fields.
     * @param bool $padded Whether the field is padded.
     * @return string The PHP statement to read the field value.
     */
    public static function getReadStatementForBasicType($type, $lengthExpression, $padded)
    {
        $paddedStr = $padded ? 'true' : 'false';

        if ($type->name() === "byte") {
            return "\$reader->getByte()";
        } elseif ($type->name() === "char") {
            return "\$reader->getChar()";
        } elseif ($type->name() === "short") {
            return "\$reader->getShort()";
        } elseif ($type->name() === "three") {
            return "\$reader->getThree()";
        } elseif ($type->name() === "int") {
            return "\$reader->getInt()";
        } elseif ($type->name() === "string") {
            if ($lengthExpression === null) {
                return "\$reader->getString()";
            } else {
                return "\$reader->getFixedString({$lengthExpression}, {$paddedStr})";
            }
        } elseif ($type->name() === "encoded_string") {
            if ($lengthExpression === null) {
                return "\$reader->getEncodedString()";
            } else {
                return "\$reader->getFixedEncodedString({$lengthExpression}, {$paddedStr})";
            }
        } else {
            throw new \AssertionError("Unhandled BasicType");
        }
    }

    /**
     * Retrieves the specific Type instance for the field based on its type string, accounting for array and length specifications.
     *
     * @return Type The type object representing the field's data type.
     */
    public function getType()
    {
        return $this->typeFactory->getType($this->typeString, $this->getTypeLength());
    }

    /**
     * Determines the length parameter for a type, handling unspecified, array, and fixed-length configurations.
     *
     * @return Length The length definition for the type.
     */
    public function getTypeLength()
    {
        if ($this->arrayField) {
            return Length::unspecified();
        }

        if ($this->lengthString !== null) {
            return Length::fromString($this->lengthString);
        }

        return Length::unspecified();
    }

    /**
     * Retrieves the canonical name for a given field type, translating internal type representations to standard PHP type names.
     *
     * @param Type $fieldType The field type object.
     * @return string The PHP type name associated with the field type.
     */
    public function getTypeName($fieldType) {
        if ($fieldType instanceof IntegerType) {
            return "int";
        }

        if ($fieldType instanceof StringType) {
            return "str";
        }

        if ($fieldType instanceof BoolType) {
            return "bool";
        }

        if ($fieldType instanceof BlobType) {
            return "bytes";
        }

        if ($fieldType instanceof CustomType) {
            return $fieldType->name();
        }

        throw new \AssertionError("Unhandled Type");
    }
}

/**
 * Calculates the maximum valid value for an integer type based on its size, supporting custom types like 'byte'.
 *
 * @param IntegerType $integerType The integer type object.
 * @return int The maximum value that can be held by the integer type.
 */
function getMaxValueOf($integerType)
{
    return $integerType->name() === 'byte' ? 255 : pow(253, $integerType->fixedSize()) - 1;
}

