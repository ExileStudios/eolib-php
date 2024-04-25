<?php

/**
 * This hash function is used by the game client to verify communication with a genuine server
 * during connection initialization.
 *
 * @param int $challenge The challenge value sent by the client.
 *                       Should be no larger than 11,092,110.
 *
 * @return int The hashed challenge value.
 *
 * @remarks The client sends an integer value to the server in the `INIT_INIT` client packet, where it
 *          is referred to as the `challenge`.
 *          The server hashes the value and sends the hash back in the `INIT_INIT` server packet.
 *          The client hashes the value and compares it to the hash sent by the server.
 *          If the hashes don't match, the client drops the connection.
 *
 * @warning Oversized challenges may result in negative hash values, which cannot be represented
 *          properly in the EO protocol.
 */
function serverVerificationHash(int $challenge): int
{
    $challenge += 1;
    return 110905
        + (_mod($challenge, 9) + 1) * _mod(11092004 - $challenge, (($challenge % 11) + 1) * 119) * 119
        + _mod($challenge, 2004);
}

/**
 * Performs a modulus operation, considering negative adjustments.
 *
 * @param int $a The dividend.
 * @param int $b The divisor.
 *
 * @return int The result of the modulus operation.
 */
function _mod(int $a, int $b): int
{
    $result = $a % $b;
    if ($a < 0) {
        $result -= $b;
    }
    return $result;
}