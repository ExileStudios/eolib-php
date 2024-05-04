<?php

namespace Protocol\Net;

use Eolib\Data\EoWriter;

/**
 * Represents a packet that can be sent or received over the network.
 */
abstract class Packet
{
    /**
     * Returns the packet family associated with this packet.
     *
     * @return int The packet family associated with this packet.
     */
    abstract public static function family(): int;

    /**
     * Returns the packet action associated with this packet.
     *
     * @return int The packet action associated with this packet.
     */
    abstract public static function action(): int;

    /**
     * Serializes and writes this packet to the provided EoWriter.
     *
     * @param EoWriter $writer The writer that this packet will be written to.
     */
    abstract public function write(EoWriter $writer): void;
}
