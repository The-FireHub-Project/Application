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
 * ### Defines an application disposer
 *
 * Establishes the contract for releasing application-specific resources and performing cleanup during
 * the application shutdown lifecycle.
 *
 * Disposers are executed during the application shutdown in their configured execution order. Each disposer
 * receives the application instance, allowing access to its context and application-managed components.
 *
 * Disposal operations should release resources safely and should not depend on the application remaining
 * operational after shutdown completes.
 *
 * This contract is independent of the kernel terminator contract and does not participate directly in
 * the kernel lifecycle.
 * @since 1.0.0
 */
interface Disposer {

    /**
     * ### Disposes of an application component
     *
     * Performs application-specific cleanup, releases allocated resources, and completes any required
     * finalization operations.
     * @since 1.0.0
     *
     * @param \FireHub\Application\Application $application <p>
     * Application being shut down.
     * </p>
     *
     * @return void
     */
    public function dispose (Application $application):void;

}