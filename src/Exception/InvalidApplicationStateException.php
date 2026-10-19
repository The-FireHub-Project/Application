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

use FireHub\Core\Exception\Runtime\KernelException;

/**
 * ### Represents an application initialization failure
 *
 * Indicates that one or more application initializers could not complete successfully.
 *
 * The original failure is preserved as the previous exception, allowing callers to inspect the underlying cause.
 *
 * An initialization failure prevents the application from entering its initialized state.
 * @since 1.0.0
 */
final class InvalidApplicationStateException extends KernelException {

    protected const string DEFAULT_MESSAGE = 'The application is in an invalid state';

}