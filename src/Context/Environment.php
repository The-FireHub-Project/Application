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

namespace FireHub\Application\Context;

/**
 * ### Defines the application execution environment
 *
 * Represents the environment in which a FireHub application is executed.
 *
 * Application environments allow the framework and its components to distinguish between production, staging,
 * development, and testing configurations without relying on arbitrary string values.
 * @since 1.0.0
 */
enum Environment {

    /**
     * ### Production environment
     *
     * Represents a live application environment intended for end users.
     * @since 1.0.0
     */
    case PRODUCTION;

    /**
     * ### Staging environment
     *
     * Represents a pre-production environment used for deployment verification and acceptance testing.
     * @since 1.0.0
     */
    case STAGING ;

    /**
     * ### Development environment
     *
     * Represents an environment used for application development, debugging, and local execution.
     * @since 1.0.0
     */
    case DEVELOPMENT;

    /**
     * ### Testing environment
     *
     * Represents an environment used for automated tests and application behavior verification.
     * @since 1.0.0
     */
    case TESTING;

}