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

use FireHub\Core\Boundary\Container as ContainerBoundary;
use FireHub\Kernel\Kernel;
use FireHub\Kernel\Lifecycle\Engine;
use FireHub\Kernel\Lifecycle\Dependency\Boundary;
use FireHub\Container\Container;
use FireHub\Module\Manager as ModuleManager;
use FireHub\Kernel\Lifecycle\Exception\ {
    InvalidLifecycleStateException, LifecycleExecutionException
};
use FireHub\Application\Exception\ {
    ApplicationDisposalException, ApplicationInitializationException, InvalidApplicationStateException
};
use Closure;

/**
 * ### Manages the application lifecycle
 *
 * Represents a FireHub application and coordinates its initialization, execution readiness, and resource disposal.
 *
 * Each application owns an independent lifecycle engine configured with application-specific states.
 *
 * Application initializers and disposers are registered independently of kernel bootstrappers and terminators.
 *
 * The parent kernel must be booted before application initialization begins. Kernel initialization and termination remain
 * explicit operations and are not automatically performed by the application.
 *
 * Lifecycle failures are exposed through application-specific exceptions.
 *
 * <code>
 * use FireHub\Application\Application;
 * use FireHub\Application\Context;
 * use FireHub\Application\Context\Name;
 * use FireHub\Application\Context\Environment;
 *
 * $app = new Application(
 *     new Context(
 *         new Name('FireHub'),
 *         Environment::PRODUCTION
 * ));
 *
 * $app->registerInitializer(new ClassThatImplementsInitializer);
 * $app->registerDisposer(new ClassThatImplementsDisposer);
 *
 * $app->kernel()->boot();
 * $app->initialize();
 *
 * // write code here
 *
 * $app->dispose();
 * $app->kernel()->shutdown();
 * </code>
 *
 * @since 1.0.0
 */
final readonly class Application {

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
     * @param \FireHub\Kernel\Kernel $kernel [optional] <p>
     * Parent kernel. A new kernel is created by default.
     * </p>
     * @param \FireHub\Core\Boundary\Container $container [optional] <p>
     * Application dependency injection container. A new container is created by default.
     * </p>
     * @param \FireHub\Module\Manager $modules [optional] <p>
     * Application module manager.
     * </p>
     *
     * @return void
     */
    public function __construct (
        private Context $context,
        private Kernel $kernel = new Kernel,
        private ContainerBoundary $container = new Container,
        private ModuleManager $modules = new ModuleManager
    ) {

        $this->lifecycle = new Engine(
            State::CREATED,
            $this->kernel->dependencyBoundary()
        );

    }

    /**
     * ### Gets the application lifecycle dependency boundary
     *
     * Exposes the application as a parent lifecycle for dependent runtime executions.
     *
     * Dependencies can only be acquired while the application is initialized.
     *
     * @since 1.0.0
     *
     * @uses \FireHub\Kernel\Lifecycle\Engine::dependencyBoundary() To create the dependency boundary.
     *
     * @return \FireHub\Kernel\Lifecycle\Dependency\Boundary Application lifecycle dependency boundary.
     */
    public function dependencyBoundary ():Boundary {

        return $this->lifecycle->dependencyBoundary(
            fn (State $state):bool => $state === State::INITIALIZED
        );

    }

    /**
     * ### Registers an application initializer
     *
     * Registers an initializer for execution during application initialization.
     *
     * Initializers are executed according to their configured execution order. Registration is permitted only before
     * initialization begins.
     *
     * <code>
     * use FireHub\Application\Application;
     * use FireHub\Application\Context;
     * use FireHub\Application\Context\Name;
     * use FireHub\Application\Context\Environment;
     *
     * $app = new Application(
     *     new Context(
     *         new Name('FireHub'),
     *         Environment::PRODUCTION
     * ));
     *
     * $app->registerInitializer(new ClassThatImplementsInitializer);
     * </code>
     *
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
     *
     * <code>
     * use FireHub\Application\Application;
     * use FireHub\Application\Context;
     * use FireHub\Application\Context\Name;
     * use FireHub\Application\Context\Environment;
     *
     * $app = new Application(
     *     new Context(
     *         new Name('FireHub'),
     *         Environment::PRODUCTION
     * ));
     *
     * $app->registerDisposer(new ClassThatImplementsDisposer);
     * </code>
     *
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
     *
     * <code>
     * use FireHub\Application\Application;
     * use FireHub\Application\Context;
     * use FireHub\Application\Context\Name;
     * use FireHub\Application\Context\Environment;
     *
     * $app = new Application(
     *     new Context(
     *         new Name('FireHub'),
     *         Environment::PRODUCTION
     * ));
     *
     * $app->initialize();
     * </code>
     *
     * @since 1.0.0
     *
     * @uses \FireHub\Kernel\Lifecycle\Engine::state() To get the current state.
     * @uses \FireHub\Kernel\Lifecycle\Engine::start() To execute registered initializers.
     * @uses \FireHub\Kernel\Lifecycle\Engine::execute() To execute a callback.
     *
     * @throws \FireHub\Application\Exception\InvalidApplicationStateException If initialization is not allowed.
     * @throws \FireHub\Application\Exception\ApplicationInitializationException If an initializer fails.
     *
     * @return void
     */
    public function initialize ():void {

        if ($this->state() === State::INITIALIZED) return;

        try {

            $this->lifecycle->execute(
                callback: fn() => $this->lifecycle->start(),
                from: [State::CREATED],
                success: State::INITIALIZED,
                failure: State::INITIALIZATION_FAILED,
                entering: State::INITIALIZING,
                acquire_parent: true
            );

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
     *
     * <code>
     * use FireHub\Application\Application;
     * use FireHub\Application\Context;
     * use FireHub\Application\Context\Name;
     * use FireHub\Application\Context\Environment;
     *
     * $app = new Application(
     *     new Context(
     *         new Name('FireHub'),
     *         Environment::PRODUCTION
     * ));
     *
     * $app->dispose();
     * </code>
     *
     * @since 1.0.0
     *
     * @uses \FireHub\Kernel\Lifecycle\Engine::state() To get the current state.
     * @uses \FireHub\Kernel\Lifecycle\Engine::execute() To execute a callback.
     * @uses \FireHub\Kernel\Lifecycle\Engine::stop() To execute registered disposers.
     *
     * @throws \FireHub\Application\Exception\InvalidApplicationStateException If disposal is not allowed.
     * @throws \FireHub\Application\Exception\ApplicationDisposalException If a disposer fails.
     *
     * @return void
     */
    public function dispose ():void {

        if ($this->state() === State::DISPOSED) return;

        try {

            $this->lifecycle->execute(
                callback: fn() => $this->lifecycle->stop(),
                from: [
                    State::CREATED,
                    State::INITIALIZED,
                    State::INITIALIZATION_FAILED
                ],
                success: State::DISPOSED,
                failure: State::DISPOSAL_FAILED,
                entering: State::DISPOSING,
                acquire_parent: true,
                release_parent: true,
                require_no_dependents: true
            );

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
     * ### Executes an application operation
     *
     * Executes a callback within an isolated dependency injection scope.
     *
     * The application must be initialized before execution begins.
     *
     * Each execution creates a new dependency injection scope and execution context.
     *
     * Scoped services are reused within the execution but remain isolated from other executions. Singleton services
     * remain shared through the originating application container.
     *
     * Acquires an application lifecycle dependency to prevent disposal while execution is active.
     *
     * The execution scope is automatically closed after the callback completes, including when an exception is thrown.
     *
     * The lifecycle dependency is released after scope cleanup.
     *
     * <code>
     * $result = $app->execute(
     *     function () use (Application $app):void {
     *
     *         $test = $app->container()->get(Test::class);
     *
     *         return '';
     *
     *     }
     * );
     * </code>
     *
     * @since 1.0.0
     *
     * @uses \FireHub\Application\Application::dependencyBoundary() To acquire a lifecycle dependency.
     * @uses \FireHub\Kernel\Lifecycle\Dependency\Lease::release() To release the lifecycle dependency.
     *
     * @param Closure(\FireHub\Application\ExecutionContext):mixed $callback <p>
     * Operation to execute.
     * </p>
     *
     * @throws \FireHub\Kernel\Lifecycle\Exception\InvalidLifecycleStateException If the application is not available.
     *
     * @return mixed Operation result.
     */
    public function execute (Closure $callback):mixed {

        $lease = $this->dependencyBoundary()->acquire();

        try {

            $scope = $this->container->createScope();

            try {

                $context = new ExecutionContext(
                    $this, $scope
                );

                return $callback($context);

            } finally {
                $scope->close();

            }

        } finally {

            $lease->release();

        }

    }

    /**
     * ### Gets the current application lifecycle state
     *
     * Returns the application's own lifecycle state directly from its lifecycle engine.
     *
     * No conversion from kernel states is performed.
     *
     * <code>
     *  use FireHub\Application\Application;
     * use FireHub\Application\Context;
     * use FireHub\Application\Context\Name;
     * use FireHub\Application\Context\Environment;
     *
     * $app = new Application(
     *     new Context(
     *         new Name('FireHub'),
     *         Environment::PRODUCTION
     * ));
     *
     * $app->state();
     *
     * // FireHub\Application\State::CREATED
     *
     * $app->initialize();
     * $app->state();
     *
     * // FireHub\Application\State::INITIALIZED
     *
     * $app->dispose();
     * $app->state();
     *
     * // FireHub\Application\State::DISPOSED
     * </code>
     *
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
     * Returns the parent kernel on which the application lifecycle depends.
     * @since 1.0.0
     *
     * @return \FireHub\Kernel\Kernel Application kernel.
     */
    public function kernel ():Kernel {

        return $this->kernel;

    }

    /**
     * ### Gets the application execution context
     *
     * <code>
     * use FireHub\Application\Application;
     * use FireHub\Application\Context;
     * use FireHub\Application\Context\Name;
     * use FireHub\Application\Context\Environment;
     *
     * $app = new Application(
     *     new Context(
     *         new Name('FireHub'),
     *         Environment::PRODUCTION
     * ));
     *
     * $kernel->context()->name->value();
     *
     * // FireHub
     *
     * $kernel->context()->environment;
     *
     * // FireHub\Application\Context\Environment::PRODUCTION
     * </code>
     *
     * @since 1.0.0
     *
     * @return \FireHub\Application\Context Application context.
     */
    public function context ():Context {

        return $this->context;

    }

    /**
     * ### Gets the application dependency injection container
     * @since 1.0.0
     *
     * @return \FireHub\Core\Boundary\Container Application container.
     */
    public function container ():ContainerBoundary {

        return $this->container;

    }

    /**
     * ### Gets the application module manager
     *
     * Returns the module manager associated with this application.
     *
     * The manager provides access to registered modules and their dependency ordering.
     * @since 1.0.0
     *
     * @return \FireHub\Module\Manager Application module manager.
     */
    public function modules ():ModuleManager {

        return $this->modules;

    }

}