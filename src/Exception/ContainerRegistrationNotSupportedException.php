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
 * ### Container registration not a supported exception
 *
 * Thrown when application composition requires service registration, but the configured dependency injection
 * container does not implement the required registration boundary.
 * @since 1.0.0
 */
final class ContainerRegistrationNotSupportedException extends RuntimeException {

    protected const string DEFAULT_MESSAGE = 'The configured dependency injection container does not support service registration';

}