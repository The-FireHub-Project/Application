<?php declare(strict_types = 1);

/**
 * This file is part of the FireHub Project ecosystem
 *
 * @author Danijel Galić <danijel.galic@outlook.com>
 * @copyright 2026-present The FireHub Project - All rights reserved
 * @license https://opensource.org/license/Apache-2-0 Apache License, Version 2.0
 *
 * @php-version >=8.5
 * @package Application
 */

namespace FireHub\Application;

use FireHub\Core\Boundary\Container\Scope;

/**
 * ### Represents an application execution context
 *
 * Provides access to the application and its isolated dependency injection scope during a single execution.
 *
 * Each execution context is associated with exactly one scope.
 *
 * Scoped services are shared within the same execution context but remain isolated from other execution contexts.
 *
 * The application owns the scope lifecycle. The scope is automatically closed when execution completes.
 * @since 1.0.0
 */
final readonly class ExecutionContext {

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param \FireHub\Application\Application $application <p>
     * Application associated with this execution.
     * </p>
     * @param \FireHub\Core\Boundary\Container\Scope $scope <p>
     * Execution dependency injection scope.
     * </p>
     *
     * @return void
     */
    public function __construct (
        private Application $application,
        private Scope $scope
    ) {}

    /**
     * ### Gets the application
     *
     * Returns the application associated with this execution context.
     * @since 1.0.0
     *
     * @return \FireHub\Application\Application Application instance.
     */
    public function application ():Application {

        return $this->application;

    }

    /**
     * ### Gets the execution dependency injection scope
     *
     * Returns the isolated scope associated with this execution.
     *
     * The scope is managed by the application and must not be manually closed by consumers.
     * @since 1.0.0
     *
     * @return \FireHub\Core\Boundary\Container\Scope Execution scope.
     */
    public function scope ():Scope {

        return $this->scope;

    }

}