<?php
namespace TYPO3\Flow\I18n\Cldr\Reader\Exception;

/*
 * This file is part of the TYPO3.Flow package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

/**
 * The "Invalid Format Length" exception
 *
 * Thrown when $formatLength parameter provided to any Readers' method is not
 * one of allowed values.
 *
 * @api
 */
class InvalidFormatLengthException extends \TYPO3\Flow\I18n\Exception\InvalidArgumentException
{
}
