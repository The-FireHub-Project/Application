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

use FireHub\Core\Exception\Runtime\InvalidArgumentException;

/**
 * ### Application kernel mismatch exception
 *
 * Thrown when attempting to register an application whose kernel differs from the kernel managed by the application
 * manager.
 * @since 1.0.0
 */
final class ApplicationKernelMismatchException extends InvalidArgumentException {

    protected const string DEFAULT_MESSAGE = 'The application kernel does not match the application manager kernel';

}