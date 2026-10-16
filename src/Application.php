<?php declare(strict_types = 1);

/**
 * This file is part of the FireHub Project ecosystem
 *
 * @author Danijel Galić <danijel.galic@outlook.com>
 * @copyright 2026-present The FireHub Project - All rights reserved
 * @license https://opensource.org/license/Apache-2-0 Apache License, Version 2.0
 *
 * @php-version >=8.2
 * @package Application
 */

namespace FireHub\Application;

use FireHub\Kernel\Kernel;

/**
 * ### Represents a FireHub application
 *
 * Provides the primary entry point for an application within the FireHub runtime.
 *
 * Maintains a dedicated kernel responsible for managing the application lifecycle.
 *
 * Application-specific services, configuration, and execution capabilities can be integrated independently
 * without extending or duplicating the kernel API.
 * @since 1.0.0
 */
final readonly class Application {

    /**
     * ### Application kernel
     * @since 1.0.0
     */
    private Kernel $kernel;

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param \FireHub\Application\Context $context <p>
     * Application context.
     * </p>
     *
     * @return void
     */
    public function __construct (
        private Context $context
    ) {

        $this->kernel = new Kernel;

    }

    /**
     * ### Gets the application kernel
     *
     * Returns the kernel responsible for managing the application lifecycle.
     * @since 1.0.0
     *
     * @return \FireHub\Kernel\Kernel Application kernel.
     */
    public function kernel ():Kernel {

        return $this->kernel;

    }

    /**
     * ### Gets the application execution context
     * @since 1.0.0
     *
     * @return \FireHub\Application\Context Application context.
     */
    public function context ():Context {

        return $this->context;

    }

}