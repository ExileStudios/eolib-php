<?php

namespace Eolib\Packet;

use Eolib\Packet\SequenceStart;

class PacketSequencer
{
    /**
     * A class for generating packet sequences.
     *
     * @var SequenceStart $_start The start of the sequence.
     * @var int $_counter The current position in the sequence.
     */
    private SequenceStart $start;
    private int $counter;

    /**
     * Constructs a new PacketSequencer with the provided SequenceStart.
     *
     * @param SequenceStart $start The sequence start.
     */
    public function __construct(SequenceStart $start)
    {
        $this->start = $start;
        $this->counter = 0;
    }

    /**
     * Returns the next sequence value, updating the sequence counter in the process.
     *
     * Note:
     * This is not a monotonic operation. The sequence counter increases from 0 to 9 before
     * looping back around to 0.
     *
     * @return int The next sequence value.
     */
    public function nextSequence(): int
    {
        $result = $this->start->getValue() + $this->counter;
        $this->counter = ($this->counter + 1) % 10;
        return $result;
    }

    /**
     * Sets the sequence start, also known as the "starting counter ID".
     *
     * Note:
     * This does not reset the sequence counter.
     *
     * @param SequenceStart $start The new sequence start.
     */
    public function setSequenceStart(SequenceStart $start): void
    {
        $this->start = $start;
    }
}
