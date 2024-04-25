<?php

namespace Eolib\Packet;

/**
 * Abstract base class for defining a sequence start value in network communication packets.
 * This class requires that any subclass implement the getValue() method to return a specific
 * sequence start value.
 */
abstract class SequenceStart
{
    /**
     * A value sent by the server to update the client's sequence start, also known as the 'starting counter ID'.
     *
     * @return int Returns the sequence start value.
     */
    abstract public function getValue(): int;

    /**
     * Returns an instance of SequenceStart with a value of 0.
     *
     * @return SequenceStart An instance of SequenceStart.
     */
    public static function zero(): SequenceStart
    {
        return new SimpleSequenceStart(0);
    }
}

/**
 * A simple implementation of SequenceStart that holds a fixed integer value.
 */
class SimpleSequenceStart extends SequenceStart
{
    private $value;

    /**
     * Constructs a SimpleSequenceStart with a specified integer value.
     *
     * @param int $value The integer value for this sequence start.
     */
    public function __construct(int $value)
    {
        $this->value = $value;
    }

    /**
     * Retrieves the integer value of this sequence start.
     *
     * @return int Returns the sequence start value.
     */
    public function getValue(): int
    {
        return $this->value;
    }
}

/**
 * Represents a sequence start value specifically for account reply packets, with potential additional functionality.
 */
class AccountReplySequenceStart extends SimpleSequenceStart
{
    /**
     * Constructs an AccountReplySequenceStart with a specified integer value.
     *
     * @param int $value The integer value for this sequence start.
     */
    public function __construct(int $value)
    {
        parent::__construct($value);
    }

    /**
     * Creates an instance from a given integer value, facilitating easy instantiation.
     *
     * @param int $value The value to use for the new sequence start.
     * @return AccountReplySequenceStart Returns a new instance with the specified value.
     */
    public static function fromValue(int $value): AccountReplySequenceStart
    {
        return new AccountReplySequenceStart($value);
    }

    /**
     * Generates a new AccountReplySequenceStart with a randomly determined integer value.
     *
     * @return AccountReplySequenceStart Returns a new instance with a random value.
     */
    public static function generate(): AccountReplySequenceStart
    {
        return new AccountReplySequenceStart(random_int(0, 239));
    }
}

/**
 * Defines a sequence start value for initialization packets, incorporating two sequence numbers.
 */
class InitSequenceStart extends SimpleSequenceStart
{
    private $seq1;
    private $seq2;

    /**
     * Constructs an InitSequenceStart with specified initial values.
     *
     * @param int $value The main sequence start value.
     * @param int $seq1 The first supplementary sequence number.
     * @param int $seq2 The second supplementary sequence number.
     */
    public function __construct(int $value, int $seq1, int $seq2)
    {
        parent::__construct($value);
        $this->seq1 = $seq1;
        $this->seq2 = $seq2;
    }

    /**
     * Retrieves the first supplementary sequence number.
     *
     * @return int The first supplementary sequence number.
     */
    public function getSeq1(): int
    {
        return $this->seq1;
    }

    /**
     * Retrieves the second supplementary sequence number.
     *
     * @return int The second supplementary sequence number.
     */
    public function getSeq2(): int
    {
        return $this->seq2;
    }

    /**
     * Creates an InitSequenceStart from two given sequence values, calculating the main sequence start value.
     *
     * @param int $seq1 The first sequence number.
     * @param int $seq2 The second sequence number.
     * @return InitSequenceStart Returns a new instance configured with the calculated sequence start value.
     */
    public static function fromInitValues(int $seq1, int $seq2): InitSequenceStart
    {
        $value = $seq1 * 7 + $seq2 - 13;
        return new InitSequenceStart($value, $seq1, $seq2);
    }

    /**
     * Generates a new InitSequenceStart with randomly determined sequence numbers.
     *
     * @return InitSequenceStart Returns a new instance with random sequence numbers.
     */
    public static function generate(): InitSequenceStart
    {
        $value = random_int(0, 1756);
        $seq1 = random_int(0, intdiv($value + 13, 7));
        $seq2 = $value - $seq1 * 7 + 13;
        return new InitSequenceStart($value, $seq1, $seq2);
    }
}

/**
 * Represents a sequence start value used in ping operations, incorporating two sequence numbers.
 */
class PingSequenceStart extends SimpleSequenceStart
{
    private $seq1;
    private $seq2;

    /**
     * Constructs a PingSequenceStart with specified initial values.
     *
     * @param int $value The main sequence start value.
     * @param int $seq1 The first supplementary sequence number.
     * @param int $seq2 The second supplementary sequence number.
     */
    public function __construct(int $value, int $seq1, int $seq2)
    {
        parent::__construct($value);
        $this->seq1 = $seq1;
        $this->seq2 = $seq2;
    }

    /**
     * Retrieves the first supplementary sequence number.
     *
     * @return int The first supplementary sequence number.
     */
    public function getSeq1(): int
    {
        return $this->seq1;
    }

    /**
     * Retrieves the second supplementary sequence number.
     *
     * @return int The second supplementary sequence number.
     */
    public function getSeq2(): int
    {
        return $this->seq2;
    }

    /**
     * Creates a PingSequenceStart from two given sequence values, calculating the main sequence start value.
     *
     * @param int $seq1 The first sequence number.
     * @param int $seq2 The second sequence number.
     * @return PingSequenceStart Returns a new instance configured with the calculated sequence start value.
     */
    public static function fromPingValues(int $seq1, int $seq2): PingSequenceStart
    {
        $value = $seq1 - $seq2;
        return new PingSequenceStart($value, $seq1, $seq2);
    }

    /**
     * Generates a new PingSequenceStart with randomly determined sequence numbers.
     *
     * @return PingSequenceStart Returns a new instance with random sequence numbers.
     */
    public static function generate(): PingSequenceStart
    {
        $value = random_int(0, 1756);
        $seq1 = $value + random_int(0, 255);
        $seq2 = $seq1 - $value;
        return new PingSequenceStart($value, $seq1, $seq2);
    }
}
