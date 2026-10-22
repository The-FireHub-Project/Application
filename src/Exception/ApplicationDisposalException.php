<?php declare(strict_types = 1);

/**
 * This file is part of the FireHub Project ecosystem
 *
 * @author Danijel Galić <danijel.galic@outlook.com>
 * @copyright 2026-present The FireHub Project - All rights reserved
 * @license https://opensource.org/license/Apache-2-0 Apache License, Version 2.0
 *
 * @php-version >=8.1
 * @package Application
 */

namespace FireHub\Application\Exception;

use FireHub\Core\Exception\RuntimeException;

/**
 * ### Represents an application disposal failure
 *
 * Indicates that one or more application disposers failed while releasing managed resources.
 *
 * Remaining disposers are attempted even when an individual disposer fails.
 *
 * The original failure is preserved as the previous exception.
 * @since 1.0.0
 */
final class ApplicationDisposalException extends RuntimeException {

    /**
     * @inheritDoc
     *
     * @since 1.0.0
     */
    protected const string DEFAULT_MESSAGE = 'The application failed to dispose';

}