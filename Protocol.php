<?php
require_once dirname(__FILE__) . '/vendor/autoload.php';
include 'ProtocolCodeGenerator/Util/DocstringUtils.php';
include 'ProtocolCodeGenerator/Util/NameUtils.php';
include 'ProtocolCodeGenerator/Util/XMLUtils.php';
include 'ProtocolCodeGenerator/Util/NumberUtils.php';

use ProtocolCodeGenerator\Generate\ProtocolCodeGenerator;

class Protocol {
    public function clean() {
        $generated = $this->generatedDir();
        if (file_exists($generated)) {
            echo "Removing: $generated\n";
            $this->_removeDirectory($generated);
        }
    }

    public function generate() {
        $code_generator = new ProtocolCodeGenerator($this->_eo_protocol_dir());
        $code_generator->generate($this->generatedDir());
    }

    private function generatedDir() {
        return dirname(__FILE__) . '/Eolib/Protocol/Generated';
    }

    private function _eo_protocol_dir() {
        return dirname(__FILE__) . '/eo-protocol/xml';
    }

    private function _removeDirectory($path) {
        $files = array_diff(scandir($path), array('.','..'));
        foreach ($files as $file) {
            (is_dir("$path/$file")) ? $this->_removeDirectory("$path/$file") : unlink("$path/$file");
        }
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
