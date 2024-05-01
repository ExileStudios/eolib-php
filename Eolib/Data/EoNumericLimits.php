<?php

namespace Eolib\Data;

/**
 * Defines the numeric limits for different types of EO data sizes.
 *
 * This file contains constant definitions for the maximum values that various
 * EO data types can hold, based on their byte length. Each constant represents
 * the maximum value that can be represented by a sequence of bytes up to the
 * size of the respective type in the EO protocol. These limits are used
 * throughout the EO system to ensure data integrity and proper range checking.
 */

/**
 * The maximum value that can be represented by a single byte (unsigned).
 *
 * @var int
 */
const EO_CHAR_MAX = 253;

/**
 * The maximum value that can be represented by two bytes (unsigned).
 *
 * @var int
 */
const EO_SHORT_MAX = EO_CHAR_MAX * EO_CHAR_MAX;

/**
 * The maximum value that can be represented by three bytes (unsigned).
 *
 * @var int
 */
const EO_THREE_MAX = EO_CHAR_MAX * EO_CHAR_MAX * EO_CHAR_MAX;

/**
 * The maximum value that can be represented by four bytes (unsigned).
 *
 * @var int
 */
const EO_INT_MAX = EO_CHAR_MAX * EO_CHAR_MAX * EO_CHAR_MAX * EO_CHAR_MAX;
