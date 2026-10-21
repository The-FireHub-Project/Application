<?php declare(strict_types = 1);

/**
 * This file is part of the FireHub Project ecosystem
 *
 * @author Danijel Galić <danijel.galic@outlook.com>
 * @copyright 2026-present The FireHub Project - All rights reserved
 * @license https://opensource.org/license/Apache-2-0 Apache License, Version 2.0
 *
 * @php-version >=7.4
 * @package Application
 */

namespace FireHub\Application;

/**
 * ### Defines an application initializer
 *
 * Establishes the contract for initializing application-specific components during the application startup
 * lifecycle.
 *
 * Initializers are executed during application boot in their configured execution order. Each initializer
 * receives the application instance, allowing access to its context and application-managed components.
 *
 * Initialization must complete successfully before later initializers are executed.
 *
 * This contract is independent of the kernel bootstrapper contract and does not participate directly in
 * the kernel lifecycle.
 * @since 1.0.0
 */
interface Initializer {

    /**
     * ### Initializes an application component
     *
     * Performs application-specific initialization and prepares the component for application execution.
     * @since 1.0.0
     *
     * @param \FireHub\Application\Application $application <p>
     * Application being initialized.
     * </p>
     *
     * @return void
     */
    public function initialize (Application $application):void;

}