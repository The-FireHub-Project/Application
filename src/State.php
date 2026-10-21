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

namespace FireHub\Application;

/**
 * ### Defines application lifecycle states
 *
 * Represents the possible states of an application throughout its initialization, execution readiness,
 * and resource disposal lifecycle.
 *
 * Application lifecycle states are independent of kernel lifecycle states and reflect the execution
 * of application initializers and disposers.
 * @since 1.0.0
 */
enum State {

    /**
     * ### Created
     *
     * The application has been instantiated but has not started initialization.
     * @since 1.0.0
     */
    case CREATED;

    /**
     * ### Initializing
     *
     * The application is currently executing its initializers and preparing for execution.
     * @since 1.0.0
     */
    case INITIALIZING;

    /**
     * ### Initialized
     *
     * The application has successfully completed initialization and is ready for execution.
     * @since 1.0.0
     */
    case INITIALIZED;

    /**
     * ### Disposing
     *
     * The application is currently executing its disposers and releasing managed resources.
     * @since 1.0.0
     */
    case DISPOSING;

    /**
     * ### Disposed
     *
     * The application has completed its disposal process and is no longer operational.
     * @since 1.0.0
     */
    case DISPOSED;

    /**
     * ### Initialization Failed
     *
     * The application failed to execute its initializers and cannot continue its normal lifecycle.
     * @since 1.0.0
     */
    case INITIALIZATION_FAILED;

    /**
     * ### Disposal Failed
     *
     * The application failed to execute its disposers and cannot complete its normal lifecycle.
     * @since 1.0.0
     */
    case DISPOSAL_FAILED;

}