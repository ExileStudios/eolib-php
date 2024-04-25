<?php

namespace ProtocolCodeGenerator\Generate;

/**
 * Represents a single deprecated field within a type, encapsulating information about the deprecation.
 */
class DeprecatedField
{
    /**
     * The name of the type that contains the deprecated field.
     *
     * @var string
     */
    public $typeName;

    /**
     * The old name of the deprecated field.
     *
     * @var string
     */
    public $oldFieldName;

    /**
     * The new name of the field that should be used instead.
     *
     * @var string
     */
    public $newFieldName;

    /**
     * The version since which the old field name is deprecated.
     *
     * @var string
     */
    public $since;

    /**
     * Constructs a new instance of a deprecated field record.
     *
     * @param string $typeName The type name containing the field.
     * @param string $oldFieldName The deprecated field name.
     * @param string $newFieldName The new field name that replaces the deprecated one.
     * @param string $since The version marking the deprecation of the field.
     */
    public function __construct($typeName, $oldFieldName, $newFieldName, $since)
    {
        $this->typeName = $typeName;
        $this->oldFieldName = $oldFieldName;
        $this->newFieldName = $newFieldName;
        $this->since = $since;
    }
}

/**
 * Manages deprecated fields across different types, providing a centralized repository to query deprecation details.
 */
class DeprecatedFields
{
    /**
     * A static cache of deprecated fields.
     *
     * @var DeprecatedField[]
     */
    private static $fields = null;

    /**
     * Retrieves a list of all deprecated fields. This method ensures the list is initialized only once.
     *
     * @return DeprecatedField[] An array of DeprecatedField instances.
     */
    public static function getFields()
    {
        if (self::$fields === null) {
            self::$fields = [
                new DeprecatedField("WalkPlayerServerPacket", "Direction", "direction", "1.1.0")
            ];
        }

        return self::$fields;
    }

    /**
     * Finds a deprecated field based on type name and new field name.
     *
     * @param string $typeName The name of the type to search within.
     * @param string $fieldName The new field name.
     * @return DeprecatedField|null The DeprecatedField instance if found, or null if no match is found.
     */
    public static function getDeprecatedField($typeName, $fieldName)
    {
        foreach (self::getFields() as $field) {
            if ($field->typeName === $typeName && $field->newFieldName === $fieldName) {
                return $field;
            }
        }

        return null;
    }
}