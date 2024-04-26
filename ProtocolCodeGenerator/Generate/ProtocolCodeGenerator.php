<?php

namespace ProtocolCodeGenerator\Generate;

use Exception;
use RuntimeException;
use SimpleXMLElement;
use ProtocolCodeGenerator\Type\EnumType;
use ProtocolCodeGenerator\Type\StructType;
use ProtocolCodeGenerator\Type\TypeFactory;

/**
 * Represents a protocol file with a specific path and loaded XML protocol data.
 */
class ProtocolFile {
    private $path;
    public $protocol;

    /**
     * Initializes a new instance of the ProtocolFile class.
     *
     * @param string $path The file system path to the protocol file.
     * @param SimpleXMLElement $protocol The loaded XML protocol data.
     */
    public function __construct($path, $protocol) {
        $this->path = $path;
        $this->protocol = $protocol;
    }

    /**
     * Computes the relative path from a specified base directory.
     *
     * @param string $baseDir The base directory to compute the relative path from.
     * @return string The relative path of the protocol file.
     */
    public function getRelativePath($baseDir) {
        $relativePath = substr($this->path, strlen($baseDir));
        $relativePath = ltrim($relativePath, '/\\');
        return str_replace('\\', '/', $relativePath);
    }
}

/**
 * Manages the generation of source files from protocol definitions.
 */
class ProtocolCodeGenerator {
    public $inputRoot;
    public $outputRoot;
    public $protocolFiles = [];
    public $exports = [];
    public $packetPaths = [];
    public $typeFactory;

    /**
     * Constructs a ProtocolCodeGenerator for handling the generation process.
     *
     * @param string $input_root The root directory containing the protocol definitions.
     */
    public function __construct(string $input_root) {
        $this->inputRoot = $input_root;
        $this->outputRoot = null;
        $this->protocolFiles = [];
        $this->exports = [];
        $this->packetPaths = [];
        $this->typeFactory = new TypeFactory();
    }

    /**
     * Generates source files from the loaded protocol definitions and outputs them to a specified directory.
     *
     * @param string $outputRoot The directory where the generated source files will be saved.
     */
    public function generate(string $outputRoot) {
        $this->outputRoot = $outputRoot;
        try {
            $this->indexProtocolFiles();
            $this->generateSourceFiles();
        } finally {
            $this->protocolFiles = [];
            $this->exports = [];
            $this->packetPaths = [];
            $this->typeFactory->clear();
        }
    }
    
    /**
     * Indexes all protocol files within the input root directory, loading them into the generator's internal structures.
     */
    public function indexProtocolFiles() {
        if (!is_dir($this->inputRoot)) {
            throw new RuntimeException("Input root must be a directory: {$this->inputRoot}");
        }
    
        $directoryIterator = new \RecursiveDirectoryIterator($this->inputRoot, \FilesystemIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($directoryIterator, \RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === "protocol.xml") {
                $protocolFilePath = $file->getPathname();
                $this->indexProtocolFile($protocolFilePath);
            }
        }
    }

    /**
     * Indexes a single protocol file, loading its content and analyzing its structure and type definitions.
     *
     * @param string $protocolFilePath The path to the protocol file to index.
     */
    private function indexProtocolFile($protocolFilePath) {
        try {
            $protocol = simplexml_load_file($protocolFilePath);
            
            if ($protocol === false || $protocol->getName() !== 'protocol') {
                throw new RuntimeException("Expected a root <protocol> element.");
            }

            $this->protocolFiles[] = new ProtocolFile($protocolFilePath, $protocol);

            $enumElements = $protocol->enum;
            $structElements = $protocol->struct;
            $packetElements = $protocol->packet;

            $sourcePath = str_replace($this->inputRoot, '', dirname($protocolFilePath));
            $pathParts = explode(DIRECTORY_SEPARATOR, trim(strtolower($sourcePath), DIRECTORY_SEPARATOR));
            $context = end($pathParts);

            foreach ($enumElements as $protocolEnum) {
                if (!$this->typeFactory->defineCustomType($protocolEnum, $sourcePath)) {
                    throw new RuntimeException("{$protocolEnum['name']} type cannot be redefined.");
                }
            }

            foreach ($structElements as $protocolStruct) {
                if (!$this->typeFactory->defineCustomType($protocolStruct, $sourcePath)) {
                    throw new RuntimeException("{$protocolStruct['name']} type cannot be redefined.");
                }
            }

            $declaredPackets = [];
            foreach ($packetElements as $protocolPacket) {
                $packetIdentifier = "{$context}_{$protocolPacket['family']}_{$protocolPacket['action']}";
                if (in_array($packetIdentifier, $declaredPackets)) {
                    throw new RuntimeException("Packet identifier {$packetIdentifier} cannot be redefined in the same file.");
                }
                $declaredPackets[] = $packetIdentifier;
                $this->packetPaths[$packetIdentifier] = ['packet' => $protocolPacket, 'path' => $sourcePath];
            }
        } catch (Exception $e) {
            throw new RuntimeException("Error processing protocol file: {$protocolFilePath}", 0, $e);
        }
    }

    /**
     * Generates source files for all indexed protocol files.
     */
    public function generateSourceFiles() {
        foreach ($this->protocolFiles as $protocolFile) {
            $this->generateSourceFile($protocolFile);
        }
    }

    /**
     * Generates a source file for a single indexed protocol file.
     *
     * @param ProtocolFile $protocolFile The protocol file for which the source file will be generated.
     */
    public function generateSourceFile(ProtocolFile $protocolFile) {
        $protocol = $protocolFile->protocol;
        
        $phpFiles = [];
        foreach ($protocol->enum as $enum) {
            $phpFiles[] = $this->generateEnum($enum);
        }
        foreach ($protocol->struct as $struct) {
            $phpFiles[] = $this->generateStruct($struct);
        }
        foreach ($protocol->packet as $packet) {
            $phpFiles[] = $this->generatePacket($packet, $protocolFile);
        }
        
        $generatedInit = new CodeBlock();
        foreach ($phpFiles as $phpFile) {
            $phpFile->write($this->outputRoot);
        }
    }

    /**
     * Generates a PHP file from an XML enum definition.
     *
     * @param SimpleXMLElement $protocolEnum The XML element representing an enum.
     * @return PHPFile The generated PHP file encapsulating the enum.
     */
    public function generateEnum($protocolEnum) {
        $typeName = $protocolEnum['name'];
        $type = $this->typeFactory->getType($typeName);
        if (!($type instanceof EnumType)) {
            throw new Exception("{$typeName} is not a valid EnumType.");
        }
    
        echo "Generating enum: {$typeName}\n";
    
        $codeBlock = new CodeBlock();
        $codeBlock->addLine("class {$typeName} {");
        $codeBlock->indent();
        if (!empty($protocolEnum['comment'])) {
            $codeBlock->addCodeBlock(generateDocstring($protocolEnum['comment']));
        }
    
        foreach ($protocolEnum->xpath("value") as $protocol_value) {
            $value_name = $protocol_value['name'];
            $value = $type->getEnumValueByName($value_name);
            if (!$value) {
                throw new RuntimeException("Unknown enum value {$value_name}");
            }
            $codeBlock->addLine("const " . strtoupper($value_name) . " = {$value->ordinalValue()};");
            if (!empty($protocol_value['comment'])) {
                $codeBlock->addCodeBlock(generateDocstring($protocol_value['comment']));
            }
        }
    
        $codeBlock->unindent();
        $codeBlock->addLine("}");
    
        $sourcePath = $type->sourcePath();
        $relativePath = $sourcePath . '/' . $typeName;
        $this->exports[] = $relativePath;
    
        return new PHPFile($relativePath . ".php", $codeBlock);
    }
    
    /**
     * Generates a PHP file from an XML struct definition.
     *
     * @param SimpleXMLElement $protocolStruct The XML element representing a struct.
     * @return PHPFile The generated PHP file encapsulating the struct.
     */
    private function generateStruct($protocolStruct)
    {
        $typeName = (string)$protocolStruct['name'];
        $type = $this->typeFactory->getType($typeName);

        if (!($type instanceof StructType)) {
            throw new RuntimeException("{$typeName} is not a valid StructType.");
        }

        echo "Generating struct: {$type->name()}\n";

        $objectCodeGenerator = new ObjectCodeGenerator($type->name(), $this->typeFactory);

        foreach (getInstructions($protocolStruct) as $instruction) {
            $objectCodeGenerator->generateInstruction($instruction);
        }

        $sourcePath = $type->sourcePath();
        $relativePath = $sourcePath . '/' . $typeName;
        $this->exports[] = $relativePath;

        if (!empty($protocolStruct['comment'])) {
            $objectCodeGenerator->data()->docstring->addCodeBlock(
                generateDocstring($protocolStruct['comment'])
            );
        }

        $codeBlock = $objectCodeGenerator->code();

        return new PHPFile($relativePath . ".php", $codeBlock);
    }

    /**
     * Generates a PHP file from an XML packet definition, handling the mapping of packet actions and families to enums.
     *
     * @param SimpleXMLElement $protocol_packet The XML element representing a packet.
     * @return PHPFile The generated PHP file encapsulating the packet.
     */
    public function generatePacket(SimpleXMLElement $protocol_packet, ProtocolFile $protocolFile) {
        $sourcePath = namespaceToPascalCase($protocolFile->getRelativePath($this->inputRoot));
        $packetPrefix = $this->makePacketPrefix($sourcePath);
        $sourcePath = namespaceToPascalCase($this->packetPaths[$packetPrefix. '_' .$protocol_packet['family'] . '_' . $protocol_packet['action']]['path']);
        $packetSuffix = $this->makePacketSuffix($sourcePath);
        $familyAttribute = (string) $protocol_packet['family'];
        $actionAttribute = (string) $protocol_packet['action'];
        $packettypeName = "{$familyAttribute}{$actionAttribute}{$packetSuffix}";

        echo "Generating packet: {$packettypeName}\n";

        $familyType = $this->typeFactory->getType("PacketFamily");
        if (!($familyType instanceof EnumType)) {
            throw new RuntimeException("PacketFamily enum is missing.");
        }

        $actionType = $this->typeFactory->getType("PacketAction");
        if (!($actionType instanceof EnumType)) {
            throw new RuntimeException("PacketAction enum is missing.");
        }

        $familyEnumValue = $familyType->getEnumValueByName($familyAttribute);
        if (!$familyEnumValue) {
            throw new RuntimeException("Unknown packet family {$familyAttribute}");
        }

        $actionEnumValue = $actionType->getEnumValueByName($actionAttribute);
        if (!$actionEnumValue) {
            throw new RuntimeException("Unknown packet action {$actionAttribute}");
        }

        $objectCodeGenerator = new ObjectCodeGenerator($packettypeName, $this->typeFactory);

        foreach (getInstructions($protocol_packet) as $instruction) {
            $objectCodeGenerator->generateInstruction($instruction);
        }

        $data = $objectCodeGenerator->data();
        $data->super_interfaces[] = "Packet";

        $data->addMethod(
            (new CodeBlock())
            ->addLine('/**')
            ->addLine(" * Returns the packet family associated with this packet.")
            ->addLine(" *")
            ->addLine(" * @return PacketFamily The packet family associated with this packet.")
            ->addLine(' */')
            ->addLine("public static function family(): int")
            ->addLine("{")
            ->indent()
            ->addLine("return PacketFamily::" . strtoupper($familyEnumValue->name()) . ";")
            ->unindent()
            ->addLine("}")
        );

        $data->addMethod(
            (new CodeBlock())
            ->addLine('/**')
            ->addLine(" * Returns the packet action associated with this packet.")
            ->addLine(" *")
            ->addLine(" * @return PacketAction The packet action associated with this packet.")
            ->addLine(' */')
            ->addLine("public static function action(): int")
            ->addLine("{")
            ->indent()
            ->addLine("return PacketAction::" . strtoupper($actionEnumValue->name()) . ";")
            ->unindent()
            ->addLine("}")
        );

        $data->addMethod(
            (new CodeBlock())
            ->addLine('/**')
            ->addLine(" * Serializes and writes this packet to the provided EoWriter.")
            ->addLine(" *")
            ->addLine(" * @param EoWriter \$writer The writer that this packet will be written to.")
            ->addLine(' */')
            ->addLine("public function write(EoWriter \$writer): void")
            ->addLine("{")
            ->indent()
            ->addLine("{$packettypeName}::serialize(\$writer, \$this);")
            ->unindent()
            ->addLine("}")
        );

        $codeBlock = new CodeBlock();

        $comment = (string) $protocol_packet->comment;
        if (!empty($comment)) {
            $codeBlock->addCodeBlock(
                generateDocstring($comment)
            );
        }
        $codeBlock->addCodeBlock($objectCodeGenerator->code());
        $codeBlock->addImport("PacketFamily", "Eolib\\Protocol\\Generated\\Net");
        $codeBlock->addImport("PacketAction", "Eolib\\Protocol\\Generated\\Net");
        $codeBlock->addImport("EoWriter", "Eolib\\Data");
        $codeBlock->addImport("EoReader", "Eolib\\Data");

        $relativePath = $sourcePath . '/' . $packettypeName;
        $this->exports[] = $relativePath;

        return new PHPFile($relativePath . ".php", $codeBlock);
    }

    /**
     * Determines the suffix for the packet class based on its network context (client or server).
     *
     * @param string $path The path segment indicating the network context.
     * @return string The suffix to append to the packet class name.
     * @throws Exception If the path does not correspond to a recognized network context.
     */
    public function makePacketSuffix($path) {
        $path = strtolower($path);
        if ($path == "\\net\\client" || $path == "/net/client") {
            return "ClientPacket";
        } elseif ($path == "\\net\\server" || $path == "/net/server") {
            return "ServerPacket";
        } else {
            throw new Exception("Cannot create packet name suffix for path {$path}");
        }
    }

    public function makePacketPrefix($path) {
        $path = strtolower($path);
        if ($path == "net/client/protocol.xml") {
            return "client";
        } elseif ($path == "net/server/protocol.xml") {
            return "server";
        } else {
            throw new Exception("Cannot create packet name prefix for path {$path}");
        }
    }
}