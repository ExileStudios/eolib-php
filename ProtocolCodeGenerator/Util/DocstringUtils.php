<?php

use ProtocolCodeGenerator\Generate\CodeBlock;

/**
 * Generates a docstring based on the protocol comment and additional notes.
 *
 * @param string $protocolComment The protocol comment to include in the docstring.
 * @param array $notes Additional notes to include in the docstring.
 * @return CodeBlock The generated docstring as a CodeBlock object.
 */
function generateDocstring($protocolComment, $notes = [])
{
    $lines = [];
    if ($protocolComment) {
        $lines = array_map('trim', explode("\n", htmlspecialchars($protocolComment)));
    }
    
    if (!empty($notes)) {
        if (!empty($lines)) {
            $lines[] = '';
        }
        $lines[] = 'Note:';
        foreach ($notes as $note) {
            $lines[] = "  - {$note}";
        }
    }
    
    $result = new CodeBlock();
    if (!empty($lines)) {
        $result->addLine("/**\n * " . implode("\n * ", $lines) . "\n */");
    }
    return $result;
}