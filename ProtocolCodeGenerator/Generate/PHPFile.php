<?php

namespace ProtocolCodeGenerator\Generate;

use ProtocolCodeGenerator\Generate\CodeBlock;

/**
 * Handles the generation and output of PHP code files, managing namespace resolution and file writing.
 */
class PHPFile {
    private $relativePath;
    private $codeBlock;
    private $moduleDocstring;

    /**
     * Constructs a PHPFile instance with specified path, code block, and optional module docstring.
     *
     * @param string $relativePath The relative path where the PHP file will be saved.
     * @param CodeBlock $codeBlock The block of code to be written to the file.
     * @param string|null $moduleDocstring Optional documentation string for the file's module.
     */
    public function __construct($relativePath, $codeBlock, $moduleDocstring = null) {
        $this->relativePath = $relativePath;
        $this->codeBlock = $codeBlock;
        $this->moduleDocstring = $moduleDocstring;
    }

    /**
     * Writes the content of the code block to a PHP file at a specified root path.
     *
     * @param string $rootPath The root path where the file will be saved, appended to the relative path.
     */
    public function write($rootPath) {
        $normalizedRelativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->relativePath);
        $outputPath = $rootPath . DIRECTORY_SEPARATOR . $normalizedRelativePath;
    
        $header = new CodeBlock();
        $header->addLine("<?php");
        $header->addLine("/**");
        $header->addLine(" * Generated from the eo-protocol XML specification.");
        $header->addLine(" *");
        $header->addLine(" * This file should not be modified.");
        $header->addLine(" * Changes will be lost when code is regenerated.");
        $header->addLine(" */");
        $header->addLine();
    
        if ($this->moduleDocstring !== null) {
            if ($this->moduleDocstring instanceof CodeBlock) {
                $header->addCodeBlock($this->moduleDocstring);
            } else {
                $header->addLine($this->moduleDocstring);
            }
            $header->addLine();
        }
    
        if (!is_dir(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0777, true);
        }
    
        // Calculate namespace based on the file path relative to the root path
        $relativeNamespace = str_replace('/', '\\', dirname($this->relativePath));
        $pascalCaseNamespace = trim($relativeNamespace, '\\');
        $namespace = "Eolib\\Protocol" . (!empty($pascalCaseNamespace) && $pascalCaseNamespace != '.' ? "\\".$pascalCaseNamespace : "");
    
        $header->setNamespace($namespace);
    
        // Ensure codeBlock is a CodeBlock instance before converting to string
        $codeBlockContent = $this->codeBlock instanceof CodeBlock ? $this->codeBlock->toString($namespace) : $this->codeBlock;
        $fileContent = $header->toString($namespace) . $codeBlockContent;
    
        file_put_contents($outputPath, $fileContent);
    }
    
    /**
     * Retrieves the relative path assigned to the PHP file.
     *
     * @return string The relative path of the PHP file.
     */
    public function getRelativePath() {
        return $this->relativePath;
    }
}