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
use FireHub\Application\Exception\ {
    ApplicationAlreadyRegisteredException, ApplicationKernelMismatchException, ApplicationNotFoundException,
    InvalidApplicationIdentifierException
};
use FireHub\Runtime;

/**
 * ### Manages multiple FireHub applications
 *
 * Coordinates the registration, initialization, and disposal of multiple applications associated with a shared kernel.
 *
 * Applications retain independent lifecycle states and dependency injection containers. The manager controls
 * application lifecycle ordering without managing individual application executions or dependency injection scopes.
 *
 * The manager owns the kernel lifecycle only when explicitly instructed to start or stop it.
 *
 * <code>
 * use FireHub\Application\Manager;
 * use FireHub\Application\Context;
 * use FireHub\Application\Context\Name;
 * use FireHub\Application\Context\Environment;
 * use FireHub\Kernel\Kernel;
 *
 * $manager = new Manager;
 *
 * $frontend = $manager->create(
 *     new Context(
 *         new Name('Frontend'),
 *         Environment::PRODUCTION
 *     ))->withContainer($container)
 *     ->build();
 *
 * $backend = $manager->create(
 *     new Context(
 *         new Name('Backend'),
 *         Environment::PRODUCTION
 *     ))->withContainer($container)
 *     ->build();
 *
 * $manager
 *     ->register('frontend', $frontend)
 *     ->register('backend', $backend);
 *
 * $manager->initializeAll();
 *
 * try {
 *
 *     $result = $manager->get('frontend')->execute(
 *         function (Application $app):string {
 *
 *         return 'Welcome to The FireHub Project!';
 *
 *     }
 * );
 *
 * } finally {
 *
 *     try {
 *
 *         $manager->disposeAll();
 *
 *     } finally {
 *
 *         $manager->shutdown();
 *
 *     }
 *
 * }
 * </code>
 *
 * @since 1.0.0
 */
final class Manager {

    /**
     * ### Registered applications
     * @since 1.0.0
     *
     * @var array<non-empty-string, \FireHub\Application\Application>
     */
    private array $applications = [];

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param \FireHub\Kernel\Kernel $kernel [optional] <p>
     * Shared application kernel.
     * </p>
     *
     * @return void
     */
    public function __construct (
        private readonly Kernel $kernel = new Kernel
    ) {}

    /**
     * ### Creates an application builder
     *
     * Creates a new application builder configured with the manager's shared kernel.
     *
     * The builder allows additional application configuration before construction.
     *
     * Building the application does not automatically register it with the manager.
     * @since 1.0.0
     *
     * @uses \FireHub\Application\Builder::create() To create the application builder.
     * @uses \FireHub\Application\Builder::withKernel() To configure the shared kernel.
     *
     * @param \FireHub\Application\Context $context <p>
     * Application context.
     * </p>
     *
     * @return \FireHub\Application\Builder Configured application builder.
     */
    public function create (Context $context):Builder {

        return Builder::create($context)
            ->withKernel($this->kernel);

    }

    /**
     * ### Registers an application
     *
     * Registers an application under the specified identifier.
     *
     * The application must use the same kernel as this manager.
     * @since 1.0.0
     *
     * @uses \FireHub\Application\Application::kernel() To get the application kernel.
     *
     * @param non-empty-string $id <p>
     * Unique application identifier.
     * </p>
     * @param \FireHub\Application\Application $application <p>
     * Application to register.
     * </p>
     *
     * @throws \FireHub\Application\Exception\InvalidApplicationIdentifierException If the identifier is empty.
     * @throws \FireHub\Application\Exception\ApplicationAlreadyRegisteredException If the application is already
     * registered.
     * @throws \FireHub\Application\Exception\ApplicationKernelMismatchException If the application kernel does not
     * match the manager kernel.
     *
     * @return $this Current manager.
     */
    public function register (string $id, Application $application):self {

        if ($id === '')
            throw new InvalidApplicationIdentifierException(
                'Application identifier cannot be empty.'
            );

        if (isset($this->applications[$id]))
            throw new ApplicationAlreadyRegisteredException;

        if ($application->kernel() !== $this->kernel)
            throw new ApplicationKernelMismatchException(
                context: [
                    'application_kernel' => $application->kernel(),
                    'kernel' => $this->kernel
                ]
            );

        $this->applications[$id] = $application;

        return $this;

    }

    /**
     * ### Gets a registered application
     * @since 1.0.0
     *
     * @param non-empty-string $id <p>
     * Application identifier.
     * </p>
     *
     * @throws \FireHub\Application\Exception\ApplicationNotFoundException If the application is not registered.
     *
     * @return \FireHub\Application\Application Registered application.
     */
    public function get (string $id):Application {

        return $this->applications[$id]
            ?? throw new ApplicationNotFoundException;

    }

    /**
     * ### Checks whether an application is registered
     * @since 1.0.0
     *
     * @param non-empty-string $id <p>
     * Application identifier.
     * </p>
     *
     * @return bool True if the application is registered.
     */
    public function has (string $id):bool {

        return isset($this->applications[$id]);

    }

    /**
     * ### Gets all registered applications
     * @since 1.0.0
     *
     * @return array<string, \FireHub\Application\Application> Registered applications.
     */
    public function all ():array {

        return $this->applications;

    }

    /**
     * ### Gets the shared kernel
     * @since 1.0.0
     *
     * @return \FireHub\Kernel\Kernel Shared application kernel.
     */
    public function kernel ():Kernel {

        return $this->kernel;

    }

    /**
     * ### Initializes a registered application
     *
     * Boots the shared kernel if necessary and initializes the specified application.
     * @since 1.0.0
     *
     * @uses \FireHub\Application\Manager::get() To get the application.
     * @uses \FireHub\Kernel\Kernel::boot() To boot the kernel.
     * @uses \FireHub\Application\Application::initialize() To initialize the application.
     *
     * @param non-empty-string $id <p>
     * Application identifier.
     * </p>
     *
     * @throws \FireHub\Application\Exception\ApplicationNotFoundException If the application is not registered.
     * @throws \FireHub\Kernel\Exception\InvalidKernelStateException If booting is not allowed.
     * @throws \FireHub\Kernel\Exception\KernelBootException If initialization fails.
     * @throws \FireHub\Application\Exception\InvalidApplicationStateException If initialization is not allowed.
     * @throws \FireHub\Application\Exception\ApplicationInitializationException If an initializer fails.
     *
     * @return void
     */
    public function initialize (string $id):void {

        $application = $this->get($id);

        $this->kernel->boot();

        $application->initialize();

    }

    /**
     * ### Disposes a registered application
     *
     * Disposes the specified application without shutting down the shared kernel.
     * @since 1.0.0
     *
     * @uses \FireHub\Application\Manager::get() To get the application.
     * @uses \FireHub\Application\Application::dispose() To dispose the application.
     *
     * @param non-empty-string $id <p>
     * Application identifier.
     * </p>
     *
     * @throws \FireHub\Application\Exception\ApplicationNotFoundException If the application is not registered.
     * @throws \FireHub\Application\Exception\InvalidApplicationStateException If disposal is not allowed.
     * @throws \FireHub\Application\Exception\ApplicationDisposalException If a disposer fails.
     *
     * @return void
     */
    public function dispose (string $id):void {

        $this->get($id)->dispose();

    }

    /**
     * ### Initializes all registered applications
     *
     * Boots the shared kernel and initializes applications in registration order.
     * @since 1.0.0
     *
     * @uses \FireHub\Kernel\Kernel::boot() To boot the kernel.
     * @uses \FireHub\Application\Application::initialize() To initialize the application.
     *
     * @throws \FireHub\Kernel\Exception\InvalidKernelStateException If booting is not allowed.
     * @throws \FireHub\Kernel\Exception\KernelBootException If initialization fails.
     * @throws \FireHub\Application\Exception\InvalidApplicationStateException If initialization is not allowed.
     * @throws \FireHub\Application\Exception\ApplicationInitializationException If an initializer fails.
     *
     * @return void
     */
    public function initializeAll ():void {

        $this->kernel->boot();

        foreach ($this->applications as $application)
            $application->initialize();

    }

    /**
     * ### Disposes all registered applications
     *
     * Disposes applications in reverse registration order.
     *
     * The shared kernel remains active until explicitly shut down.
     * @since 1.0.0
     *
     * @uses \FireHub\Application\Application::dispose() To dispose the application.
     * @uses \FireHub\Runtime\Arr\Transform::reverse() To reverse the application registration order.
     *
     * @throws \FireHub\Application\Exception\InvalidApplicationStateException If disposal is not allowed.
     * @throws \FireHub\Application\Exception\ApplicationDisposalException If a disposer fails.
     *
     * @return void
     */
    public function disposeAll ():void {

        foreach (Runtime\Arr\Transform::reverse($this->applications) as $application)
            $application->dispose();

    }

    /**
     * ### Shuts down the shared kernel
     *
     * Shuts down the kernel after all applications have been disposed.
     *
     * The kernel lifecycle prevents shutdown while active application dependencies remain.
     * @since 1.0.0
     *
     * @uses \FireHub\Kernel\Kernel::shutdown() To shutdown the kernel.
     *
     * @throws \FireHub\Kernel\Exception\InvalidKernelStateException If shutdown is not allowed.
     * @throws \FireHub\Kernel\Exception\KernelShutdownException If termination fails.
     *
     * @return void
     */
    public function shutdown ():void {

        $this->kernel->shutdown();

    }

}