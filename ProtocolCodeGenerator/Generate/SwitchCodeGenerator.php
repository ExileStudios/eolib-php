<?php

namespace ProtocolCodeGenerator\Generate;

use ProtocolCodeGenerator\Generate\CodeBlock;
use ProtocolCodeGenerator\Type\EnumType;
use ProtocolCodeGenerator\Type\IntegerType;

/**
 * Handles the generation of code for switch statements in the protocol.
 */
class SwitchCodeGenerator
{
    private $fieldName;
    private $typeFactory;
    private $context;
    private $data;

    /**
     * Constructs a new SwitchCodeGenerator instance with the provided field name, type factory, context, and data.
     *
     * @param string $fieldName The name of the field being switched on.
     * @param mixed $typeFactory The type factory for the field.
     * @param mixed $context The context of the field.
     * @param mixed $data The data associated with the field.
     */
    public function __construct($fieldName, $typeFactory, $context, $data)
    {
        $this->fieldName = snakeCaseToCamelCase($fieldName);
        $this->typeFactory = $typeFactory;
        $this->context = $context;
        $this->data = $data;
    }

    /**
     * Generates the data interface for all cases within a switch statement, defining a union type of possible data classes.
     *
     * @param array $protocolCases Array of XML elements representing case instructions.
     */
    public function generateCaseDataInterface($protocolCases)
    {
        if (empty($protocolCases)) {
            return;
        }
        $unionTypeNames = [];
        foreach ($protocolCases as $case) {
            if (count(getInstructions($case)) > 0) {
                $unionTypeNames[] = "'" . $this->getCaseDataTypeName($case) . "'";
            }
        }
        $unionTypeNames[] = "null";

        $unionType = implode(" | ", $unionTypeNames);
        $fieldName = $this->fieldName;

        $this->data->addAuxiliaryType(
            (new CodeBlock())
                ->addLine("/**")
                ->addLine(" * Data associated with different values of the `{$fieldName}` field.")
                ->addLine(" */")
                ->addLine("interface {$this->getInterfaceTypeName()} {}")
        );
    }

    /**
     * Generates the field and accessors for the case data based on the switch field within the generated class.
     */
    public function generateCaseDataField()
    {
        $interfaceTypeName = "{$this->data->className}::{$this->getInterfaceTypeName()}";
        $caseDataFieldName = $this->getCaseDataFieldName();
        $switchFieldName = $this->fieldName;

        $this->data->fields->addLine("private ?$caseDataFieldName \${$caseDataFieldName} = null;");
        $this->data->addMethod(
            (new CodeBlock())
                ->addLine("public function {$caseDataFieldName}(): ?{$caseDataFieldName}")
                ->addLine("{")
                ->indent()
                ->addLine('/**')
                ->addLine(
                    " * {$interfaceTypeName}: Gets or sets the data associated with the "
                    . "`{$switchFieldName}` field."
                )
                ->addLine(' */')
                ->addLine("return \$this->{$caseDataFieldName};")
                ->unindent()
                ->addLine("}")
        );
        $setterMethodName = "set" . ucfirst($caseDataFieldName);
        $this->data->addMethod(
            (new CodeBlock())
                ->addLine("public function {$setterMethodName}(\${$caseDataFieldName}): void")
                ->addLine("{")
                ->indent()
                ->addLine("\$this->{$caseDataFieldName} = \${$caseDataFieldName};")
                ->unindent()
                ->addLine("}")
        );
        $this->data->reprFields[] = $caseDataFieldName;
    }

    /**
     * Generates serialization and deserialization logic for a specific case in a switch structure.
     *
     * @param SimpleXMLElement $protocolCase The XML element representing a case.
     * @param bool $start Indicates if this is the first case in the switch, affecting control flow generation.
     * @return ObjectGenerationContext The context modified by the operations within this case.
     */
    public function generateCase($protocolCase, $start)
    {
        $caseDataTypeName = $this->getCaseDataTypeName($protocolCase);
        $caseContext = clone $this->context;
        $caseContext->accessibleFields = [];
        $caseContext->lengthFieldIsReferencedMap = [];

        $default = getBooleanAttribute($protocolCase, "default");

        if ($default) {
            if ($start) {
                throw new \RuntimeException("Standalone default case is not allowed.");
            }
            $controlFlow = "else";
        } else {
            $keyword = $start ? 'if' : 'elseif';
            $switchValueExpression = "\$data->{$this->fieldName}";
            $caseValueExpression = $this->getCaseValueExpression($protocolCase);
            $controlFlow = "{$keyword} ({$switchValueExpression} === {$caseValueExpression})";
        }

        $this->data->serialize->beginControlFlow($controlFlow);
        $this->data->deserialize->beginControlFlow($controlFlow);

        $fieldToStringExpression = $this->getFieldData()->type instanceof EnumType
            ? "{$this->getFieldData()->type->name()}(\$data->{$this->fieldName})->name"
            : "strval(\$data->{$this->fieldName})";

        if (getInstructions($protocolCase) === []) {
            $this->data->serialize->beginControlFlow(
                "if (\$data->{$this->getCaseDataFieldName()} !== null)"
            );
            $this->data->serialize->addLine(
                'throw new \\Eolib\\Protocol\\SerializationError('
                . "\"Expected {$this->getCaseDataFieldName()} to be null for {$this->fieldName} \""
                . " . {$fieldToStringExpression} . \".\");"
            );
            $this->data->serialize->endControlFlow();

            $this->data->deserialize->addLine("\$data->{$this->getCaseDataFieldName()} = null;");
        } else {
            $this->data->addAuxiliaryType(
                $this->generateCaseDataType($protocolCase, $caseDataTypeName, $caseContext)
            );
            $this->data->serialize->beginControlFlow(
                "if (!(\$data->{$this->getCaseDataFieldName()} instanceof {$caseDataTypeName}))"
            );
            $this->data->serialize->addLine(
                'throw new \\Eolib\\Protocol\\SerializationError('
                . "\"Expected {$this->getCaseDataFieldName()} to be of type {$caseDataTypeName} "
                . "for {$this->fieldName} \" . {$fieldToStringExpression} . \".\");"
            );
            $this->data->serialize->endControlFlow();
            $this->data->serialize->addLine(
                "{$caseDataTypeName}::serialize(\$writer, \$data->{$this->getCaseDataFieldName()});"
            );

            $this->data->deserialize->addLine(
                "\$data->{$this->getCaseDataFieldName()} = {$caseDataTypeName}::deserialize(\$reader);"
            );
        }

        $this->data->serialize->endControlFlow();
        $this->data->deserialize->endControlFlow();

        return $caseContext;
    }

    /**
     * Generates the specific data type class for a given case, used when the case requires specific data handling.
     *
     * @param SimpleXMLElement $protocolCase The XML element defining the case.
     * @param string $caseDataTypeName The name of the data type class to generate.
     * @param ObjectGenerationContext $caseContext The context specific to this case.
     * @return CodeBlock The generated code for the case data type class.
     */
    public function generateCaseDataType($protocolCase, $caseDataTypeName, $caseContext)
    {
        $objectCodeGenerator = new ObjectCodeGenerator(
            $caseDataTypeName,
            $this->typeFactory,
            $caseContext
        );

        foreach (getInstructions($protocolCase) as $instruction) {
            $objectCodeGenerator->generateInstruction($instruction);
        }

        $default = getBooleanAttribute($protocolCase, "default");

        if ($default) {
            $comment = "Default data associated with {$this->fieldName}";
        } else {
            $caseValueExpression = $this->getCaseValueExpression($protocolCase);
            $comment = "Data associated with {$this->fieldName} value {$caseValueExpression}";
        }

        $protocolComment = (string) $protocolCase->comment;
        if (!empty($protocolComment)) {
            $comment .= "\n\n";
            $comment .= $protocolComment;
        }

        $objectCodeGenerator->data()->docstring = generateDocstring($comment);
        $objectCodeGenerator->data()->superInterfaces[] = $this->getInterfaceTypeName();
        return $objectCodeGenerator->code();
    }

    /**
     * Retrieves field data for the switch's field from the context, ensuring it is accessible.
     *
     * @return FieldData The data of the field being switched on.
     * @throws RuntimeException If the field data is not accessible or does not exist.
     */
    private function getFieldData()
    {
        $result = $this->context->accessibleFields[$this->fieldName] ?? null;
        if ($result === null) {
            throw new \RuntimeException("Referenced {$this->fieldName} is not accessible.");
        }
        return $result;
    }

    /**
     * Generates a consistent interface name derived from the switch's field name to represent possible case data types.
     *
     * @return string The name of the interface that will be implemented by all case data types.
     */
    private function getInterfaceTypeName()
    {
        return camelCaseToPascalCase($this->fieldName) . "Data";
    }

    /**
     * Constructs the field name that will hold the case-specific data in the generated class.
     *
     * @return string The name of the case data field.
     */
    private function getCaseDataFieldName()
    {
        return snakeCaseToCamelCase($this->fieldName) . "Data";
    }

    /**
     * Constructs a data type name for a specific case based on whether it is a default case or has a specific value.
     *
     * @param SimpleXMLElement $protocolCase The XML element representing the case.
     * @return string The name of the data type class for the case.
     */
    public function getCaseDataTypeName($protocolCase)
    {
        $isDefault = getBooleanAttribute($protocolCase, "default");
        return $this->getInterfaceTypeName()
            . ($isDefault ? "Default" : getRequiredStringAttribute($protocolCase, "value"));
    }

    /**
     * Generates an expression to evaluate the case's value, converting protocol-specific strings to appropriate PHP expressions.
     *
     * @param SimpleXMLElement $protocolCase The XML element defining the case value.
     * @return string The expression used in the PHP code to evaluate the case.
     * @throws RuntimeException If the case value is not appropriate for the field's type.
     */
    private function getCaseValueExpression($protocolCase)
    {
        $fieldData = $this->getFieldData();

        if ($fieldData->array) {
            throw new \RuntimeException(
                "\"{$this->fieldName}\" field referenced by switch must not be an array."
            );
        }

        $fieldType = $fieldData->type;
        $caseValue = getRequiredStringAttribute($protocolCase, "value");

        if ($fieldType instanceof IntegerType) {
            if (!ctype_digit($caseValue)) {
                throw new \RuntimeException("\"{$caseValue}\" is not a valid integer value.");
            }
            return $caseValue;
        }

        if ($fieldType instanceof EnumType) {
            $ordinalValue = is_numeric($caseValue) ? intval($caseValue) : null;
            if ($ordinalValue !== null) {
                $enumValue = $fieldType->getEnumValueByOrdinal($ordinalValue);
                if ($enumValue !== null) {
                    throw new \RuntimeException(
                        "{$fieldType->name()} value {$caseValue} "
                        . "must be referred to by name ({$enumValue->name()})"
                    );
                }
                return $caseValue;
            }

            $enumValue = $fieldType->getEnumValueByName($caseValue);
            if ($enumValue === null) {
                throw new \RuntimeException(
                    "\"{$caseValue}\" is not a valid value for enum type {$fieldType->name()}."
                );
            }
            $enumValueName = strtoupper($enumValue->name());
            return "{$fieldType->name()}::{$enumValueName}";
        }

        throw new \RuntimeException(
            "{$this->fieldName} field referenced by switch must be a numeric or enumeration type."
        );
    }
}