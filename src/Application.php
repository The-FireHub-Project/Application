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

use FireHub\Kernel\Kernel;
use FireHub\Kernel\Lifecycle\ {
    Engine, States
};
use FireHub\Kernel\Lifecycle\Exception\ {
    InvalidLifecycleStateException, LifecycleExecutionException
};
use FireHub\Application\Exception\ {
    ApplicationDisposalException, ApplicationInitializationException, InvalidApplicationStateException
};

/**
 * ### Manages the application lifecycle
 *
 * Represents a FireHub application and coordinates its initialization, execution readiness, and resource disposal.
 *
 * Each application owns an independent lifecycle engine configured with application-specific states.
 *
 * Application initializers and disposers are registered independently of kernel bootstrappers and terminators.
 *
 * The kernel must be booted before application initialization begins. Kernel initialization and termination remain
 * explicit operations and are not automatically performed by the application.
 *
 * Lifecycle failures are exposed through application-specific exceptions.
 * @since 1.0.0
 */
final readonly class Application {

    /**
     * ### Application kernel
     * @since 1.0.0
     */
    private Kernel $kernel;

    /**
     * ### Application lifecycle engine
     * @since 1.0.0
     *
     * @var Engine<State>
     */
    private Engine $lifecycle;

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @uses \FireHub\Kernel\Kernel::dependencyBoundary() To create a kernel dependency boundary.
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

        $this->lifecycle = new Engine(
            new States(
                State::CREATED,
                State::INITIALIZING,
                State::INITIALIZED,
                State::DISPOSING,
                State::DISPOSED,
                State::INITIALIZATION_FAILED,
                State::DISPOSAL_FAILED
            ),
            $this->kernel->dependencyBoundary()
        );

    }

    /**
     * ### Registers an application initializer
     *
     * Registers an initializer for execution during application initialization.
     *
     * Initializers are executed according to their configured execution order. Registration is permitted only before
     * initialization begins.
     * @since 1.0.0
     *
     * @uses \FireHub\Kernel\Lifecycle\Engine::registerStartup() To register an initializer.
     * @uses \FireHub\Application\Initializer::initialize() To execute the initializer.
     *
     * @param \FireHub\Application\Initializer $initializer <p>
     * Initializer to register.
     * </p>
     * @param int $order [optional] <p>
     * Execution order.
     * </p>
     *
     * @throws \FireHub\Application\Exception\InvalidApplicationStateException If registration is not allowed.
     *
     * @return void
     */
    public function registerInitializer (Initializer $initializer, int $order = 0):void {

        try {

            $this->lifecycle->registerStartup(
                function () use ($initializer):void {

                    $initializer->initialize($this);

                },
                $order
            );

        } catch (InvalidLifecycleStateException $exception) {

            throw new InvalidApplicationStateException(
                'Cannot register an initializer in the current application state.',
                previous: $exception
            );

        }

    }

    /**
     * ### Registers an application disposer
     *
     * Registers a disposer for execution during application disposal.
     *
     * Disposers are executed according to their configured execution order. Registration is permitted only before
     * initialization begins.
     * @since 1.0.0
     *
     * @uses \FireHub\Kernel\Lifecycle\Engine::registerShutdown() To register a disposer.
     * @uses \FireHub\Application\Disposer::dispose() To execute the disposer.
     *
     * @param \FireHub\Application\Disposer $disposer <p>
     * Disposer to register.
     * </p>
     * @param int $order <p>
     * [optional] Execution order.
     * </p>
     *
     * @throws \FireHub\Application\Exception\InvalidApplicationStateException If registration is not allowed.
     *
     * @return void
     */
    public function registerDisposer (Disposer $disposer, int $order = 0):void {

        try {

            $this->lifecycle->registerShutdown(
                function () use ($disposer):void {

                    $disposer->dispose($this);

                },
                $order
            );

        } catch (InvalidLifecycleStateException $exception) {

            throw new InvalidApplicationStateException(
                'Cannot register a disposer in the current application state.',
                previous: $exception
            );

        }

    }

    /**
     * ### Initializes the application
     *
     * Executes registered initializers and prepares the application for execution.
     *
     * The kernel must be fully booted before application initialization can begin.
     *
     * Initialization stops immediately when an initializer fails.
     *
     * Calling this method after successful initialization has no effect.
     * @since 1.0.0
     *
     * @uses \FireHub\Kernel\Lifecycle\Engine::start() To execute registered initializers.
     *
     * @throws \FireHub\Application\Exception\InvalidApplicationStateException If initialization is not allowed.
     * @throws \FireHub\Application\Exception\ApplicationInitializationException If an initializer fails.
     *
     * @return void
     */
    public function initialize ():void {

        try {

            $this->lifecycle->start();

        } catch (InvalidLifecycleStateException $exception) {

            throw new InvalidApplicationStateException(
                'Cannot initialize the application in the current state.',
                previous: $exception
            );

        } catch (LifecycleExecutionException $exception) {

            throw new ApplicationInitializationException(
                previous: $exception->getPrevious() ?? $exception
            );

        }

    }

    /**
     * ### Disposes the application
     *
     * Executes registered disposers and releases resources managed by the application.
     *
     * Disposal may be performed after successful initialization, after initialization failure, or before
     * initialization begins.
     *
     * All registered disposers are attempted even when an individual disposer fails.
     *
     * Calling this method after successful disposal has no effect.
     * @since 1.0.0
     *
     * @uses \FireHub\Kernel\Lifecycle\Engine::stop() To execute registered disposers.
     *
     * @throws \FireHub\Application\Exception\InvalidApplicationStateException If disposal is not allowed.
     * @throws \FireHub\Application\Exception\ApplicationDisposalException If a disposer fails.
     *
     * @return void
     */
    public function dispose ():void {

        try {

            $this->lifecycle->stop();

        } catch (InvalidLifecycleStateException $exception) {

            throw new InvalidApplicationStateException(
                'Cannot dispose the application in the current state.',
                previous: $exception
            );

        } catch (LifecycleExecutionException $exception) {

            throw new ApplicationDisposalException(
                previous: $exception->getPrevious() ?? $exception
            );

        }

    }

    /**
     * ### Gets the current application lifecycle state
     *
     * Returns the application's own lifecycle state directly from its lifecycle engine.
     *
     * No conversion from kernel states is performed.
     * @since 1.0.0
     *
     * @uses \FireHub\Kernel\Lifecycle\Engine::state() To get the current state.
     *
     * @return \FireHub\Application\State Current application lifecycle state.
     */
    public function state ():State {

        return $this->lifecycle->state();

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