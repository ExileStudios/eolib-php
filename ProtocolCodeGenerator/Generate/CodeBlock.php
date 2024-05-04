<?php

namespace ProtocolCodeGenerator\Generate;

use ProtocolCodeGenerator\Generate\CodeBlock;

/**
 * Manages import statements for generating PHP code. This class helps in handling namespaces and paths
 * for generating correct PHP use statements in generated code.
 */
class Import {
    private $importName;
    private $absolutePackagePath;

    /**
     * Constructs an Import instance with specified import name and the absolute package path.
     *
     * @param string $importName The name of the class or package to be imported.
     * @param string $absolutePackagePath The full package path from which the import is made.
     */
    public function __construct($importName, $absolutePackagePath) {
        $this->importName = $importName;
        $this->absolutePackagePath = $absolutePackagePath;
    }

    /**
     * Converts the absolute package path to a relative path based on the current package path.
     *
     * @param string $packagePath The current package path to which the import path should be relative.
     * @return string The relative import statement for use in generated code.
     */
    public function relativize($packagePath) {
        $fromPackagePath = $this->absolutePackagePath;

        if (strpos($this->absolutePackagePath, 'eolib.') === 0) {
            $absoluteParts = explode('.', $this->absolutePackagePath);
            $baseParts = explode('.', $packagePath);

            while (count($absoluteParts) > 0 && count($baseParts) > 0 && $absoluteParts[0] === $baseParts[0]) {
                array_shift($absoluteParts);
                array_shift($baseParts);
            }

            $fromPackagePath = namespaceToPascalCase(str_repeat('../', count($baseParts)) . implode('.', $absoluteParts));
        }

        return "use {$fromPackagePath}\\{$this->importName};";
    }

    /**
     * Retrieves a unique key that identifies this import, usually combining package path and import name.
     *
     * @return string The unique key for this import.
     */
    public function getKey() {
        return $this->absolutePackagePath . '\\' . $this->importName;
    }
}

/**
 * Represents a block of code in the code generation process. It allows for adding, indenting, and managing
 * lines of code efficiently with support for namespaces and imports.
 */
class CodeBlock {
    private $namespace;
    private $imports = [];
    private $lines = [""];
    private $indentation = 0;

    /**
     * Adds a line of code or several lines of code to the current block, handling indentation automatically.
     *
     * @param string $code The code to add.
     * @return CodeBlock Returns this instance for method chaining.
     */
    public function add($code) {
        $parts = explode("\n", $code);
        for ($i = 0; $i < count($parts); $i++) {
            if (strlen(trim($parts[$i])) > 0) {
                $lineIndex = count($this->lines) - 1;
                if (strlen($this->lines[$lineIndex]) == 0) {
                    $this->lines[$lineIndex] = str_repeat("    ", $this->indentation);
                }
                $this->lines[$lineIndex] .= $parts[$i];
            }
            if ($i != count($parts) - 1) {
                $this->lines[] = "";
            }
        }
        return $this;
    }

    /**
     * Adds a single line of code to the block and appends a newline character at the end.
     *
     * @param string $line The line of code to add. If empty, adds a blank line.
     * @return CodeBlock Returns this instance for method chaining.
     */
    public function addLine($line = "") {
        $this->add($line . "\n");
        return $this;
    }

    /**
     * Retrieves the lines of code in the block, which can be used for further processing or output.
     *
     * @return string[] The lines of code in the block.
     */
    public function getLines(): array {
        return $this->lines;
    }

    /**
     * Sets the namespace for this code block, which will be prefixed to the generated code.
     *
     * @param string $namespace The namespace to set for this code block.
     */
    public function setNamespace($namespace) {
        $this->namespace = $namespace;
    }

    /**
     * Merges another CodeBlock into this one, combining their lines and imports.
     *
     * @param CodeBlock $block The other CodeBlock to merge into this one.
     * @return CodeBlock Returns this instance for method chaining.
     */
    public function addCodeBlock(CodeBlock $block) {
        $this->imports = array_merge($this->imports, $block->imports);
        foreach ($block->lines as $line) {
            $this->addLine($line);
        }
        return $this;
    }

    /**
     * Adds an import statement to the code block.
     *
     * @param string $importName The name of the class or namespace to import.
     * @param string $absolutePackagePath The absolute path to the package of the import.
     * @return CodeBlock Returns this instance for method chaining.
     */
    public function addImport($importName, $absolutePackagePath) {
        $import = new Import($importName, $absolutePackagePath);
        $importKey = $import->getKey();
        $this->imports[$importKey] = $import;
        return $this;
    }
    
    /**
     * Adds an import statement to the code block based on a custom type.
     *
     * @param CustomType $customType The custom type to import.
     * @return CodeBlock Returns this instance for method chaining.
     */
    public function addImportByType($customType)
    {
        $namespacePath = str_replace('/', '\\', $customType->sourcePath());
        $namespacePath = trim($namespacePath, '\\');

        if (!empty($namespacePath)) {
            $namespacePath .= '\\';
        }

        $fullNamespacePath = 'Eolib\\Protocol';
        if (!empty($namespacePath)) {
            $fullNamespacePath .= '\\';
        }

        $fullNamespacePath .= trim($namespacePath, '\\');

        $this->addImport($customType->name(), $fullNamespacePath);
        return $this;
    }

    /**
     * Begins a control flow structure (like if, for, while) and handles the indentation automatically.
     *
     * @param string $controlFlow The control flow statement to begin.
     * @return CodeBlock Returns this instance for method chaining.
     */
    public function beginControlFlow($controlFlow) {
        $this->addLine("$controlFlow");
        $this->addLine("{");
        $this->indent();
        return $this;
    }

    /**
     * Continues a control flow with an alternate or subsequent structure (like else, else if, catch).
     *
     * @param string $controlFlow The control flow statement to continue with.
     * @return CodeBlock Returns this instance for method chaining, facilitating the addition of further control structures.
     */
    public function nextControlFlow($controlFlow) {
        $this->unindent();
        return $this->beginControlFlow($controlFlow);
    }

    /**
     * Ends a control flow structure and decreases the indentation level appropriately, signifying the end of a block of code.
     *
     * @return CodeBlock Returns this instance for method chaining, allowing for immediate continuation of code writing.
     */
    public function endControlFlow() {
        $this->unindent();
        $this->addLine("}");
        return $this;
    }

    /**
     * Increases the indentation level for subsequent lines of code, used to maintain proper code formatting.
     *
     * @return CodeBlock Returns this instance for method chaining, ensuring fluent code writing with correct indentation.
     */
    public function indent() {
        $this->indentation++;
        return $this;
    }

    /**
     * Decreases the indentation level for subsequent lines of code, ensuring that the code structure is properly aligned.
     *
     * @return CodeBlock Returns this instance for method chaining, facilitating the addition of code at the new indentation level.
     */
    public function unindent() {
        if ($this->indentation > 0) {
            $this->indentation--;
        }
        return $this;
    }

    /**
     * Determines whether the code block is empty, which is typically used to decide whether to add additional content or not.
     *
     * @return bool Returns true if the code block contains no significant content, otherwise false.
     */
    public function isEmpty() {
        return count($this->lines) === 1 && mb_strlen($this->lines[0]) === 0;
    }

    /**
     * Converts the entire code block into a formatted string representation, including namespace declarations and import statements.
     * This method is crucial for generating the final output in the desired structure.
     *
     * @param string $packagePath The base package path used to relativize imports, adapting them to the context of the usage.
     * @return string The complete code block as a string, ready to be used or saved in files.
     */
    public function toString($packagePath) {
        $result = "";
        $importStrings = array_map(function($import) use ($packagePath) {
            return $import->relativize($packagePath);
        }, $this->imports);

        sort($importStrings);

        foreach ($importStrings as $import) {
            $result .= $import . "\n";
        }

        if (!$this->isEmpty()) {
            if (strlen($result) > 0) {
                $result .= "\n";
            }

            $result .= implode("\n", $this->lines);
        }

        if (!empty($this->namespace)) {
            $result .= "namespace {$this->namespace};\n\n";
        }

        return $result;
    }
}
