<?php
require_once dirname(__FILE__) . '/vendor/autoload.php';
include 'ProtocolCodeGenerator/Util/DocstringUtils.php';
include 'ProtocolCodeGenerator/Util/NameUtils.php';
include 'ProtocolCodeGenerator/Util/XMLUtils.php';
include 'ProtocolCodeGenerator/Util/NumberUtils.php';

use ProtocolCodeGenerator\Generate\ProtocolCodeGenerator;

class Protocol {
    private $excludeFiles = [
        'Net/Packet.php',
        'SerializationError.php'
    ];

    public function clean() {
        $generated = $this->generatedDir();
        if (file_exists($generated)) {
            echo "Removing: $generated\n";  
            $this->removeDirectory($generated);
        }
    }

    public function generate() {
        $code_generator = new ProtocolCodeGenerator($this->eoProtocolDir());
        $code_generator->generate($this->generatedDir());
    }

    private function generatedDir() {
        return dirname(__FILE__) . '/Eolib/Protocol';
    }

    private function eoProtocolDir() {
        return dirname(__FILE__) . '/eo-protocol/xml';
    }

    private function removeDirectory($path, $subPath = '') {
        $files = array_diff(scandir($path), array('.','..'));
        foreach ($files as $file) {
            $fullPath = "$path/$file";
            $relativePath = $subPath . $file;
            
            if (in_array($relativePath, $this->excludeFiles)) {
                echo "Excluding from deletion: $relativePath\n";
                continue;
            }

            if (is_dir($fullPath)) {
                $this->removeDirectory($fullPath, $relativePath . '/');
            } else {
                echo "Deleting file: $fullPath\n";
                unlink($fullPath);
            }
        }
        
        if (!empty($subPath) && in_array(rtrim($subPath, '/'), $this->excludeFiles)) {
            echo "Excluding directory from deletion: $path\n";
            return;
        }

        echo "Removing directory: $path\n";
        return rmdir($path);
    }
}

if ($argc > 1) {
    $protocol = new Protocol();
    switch ($argv[1]) {
        case "clean":
            $protocol->clean();
            break;
        case "generate":
            $protocol->clean();
            $protocol->generate();
            break;
        default:
            echo "Unknown command. Valid commands are 'generate' and 'clean'.\n";
    }
}
