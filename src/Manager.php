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
 * Coordinates the registration, initialization, execution lifecycle, and disposal of multiple applications
 * associated with a shared kernel.
 *
 * Applications retain independent lifecycle states and dependency injection containers while sharing the same
 * kernel instance. The manager coordinates application lifecycle ordering without managing individual executions
 * or dependency injection scopes.
 *
 * Applications can be registered explicitly using register() or configured and registered through managed builders
 * created using application().
 *
 * The bootstrap() operation boots the shared kernel and initializes all registered applications in registration
 * order. Applications are disposed in reverse registration order using disposeAll().
 *
 * The shared kernel remains active until shutdown() is explicitly invoked.
 *
 * <code>
 * use FireHub\Application\Application;
 * use FireHub\Application\Context;
 * use FireHub\Application\Context\Name;
 * use FireHub\Application\Context\Environment;
 * use FireHub\Application\ExecutionContext;
 * use FireHub\Application\Manager;
 *
 * $manager = new Manager;
 *
 * $manager->application(
 *     'frontend',
 *     new Context(
 *         new Name('Frontend'),
 *         Environment::PRODUCTION
 *     ))->withModule(new FrontendModule)
 *     ->withInitializer(new FrontendInitializer)
 *     ->withDisposer(new FrontendDisposer)
 *     ->register();
 *
 * $manager->application(
 *     'backend',
 *     new Context(
 *         new Name('Backend'),
 *         Environment::PRODUCTION
 *     ))->withModule(new BackendModule)
 *     ->register();
 *
 * try {
 *
 *     $manager->bootstrap();
 *
 *     $manager->get('frontend')->execute(
 *         function (ExecutionContext $execution):void {
 *
 *             $service = $execution->scope()->get(MyService::class);
 *
 *             $service->run();
 *
 *         }
 *     );
 *
 * } finally {
 *
 *     $manager->shutdown();
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
     * @throws \FireHub\Application\Exception\ApplicationKernelMismatchException If the kernel is not shared with the
     * manager.
     *
     * @return \FireHub\Application\Builder Configured application builder.
     */
    public function create (Context $context):Builder {

        return Builder::create($context)->withKernel($this->kernel);

    }

    /**
     * ### Creates a managed application builder
     *
     * Creates a builder associated with this manager and assigns the specified registration identifier.
     *
     * The resulting builder can register the constructed application directly with this manager.
     * @since 1.0.0
     *
     * @uses \FireHub\Application\Builder::managed() To create the application builder.
     *
     * @param non-empty-string $id <p>
     * Application registration identifier.
     * </p>
     * @param \FireHub\Application\Context $context <p>
     * Application context.
     * </p>
     *
     * @throws \FireHub\Application\Exception\InvalidApplicationIdentifierException If the identifier is empty.
     *
     * @return \FireHub\Application\Builder Managed application builder.
     */
    public function application (string $id, Context $context):Builder {

        return Builder::managed($context, $this, $id);

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
     * ### Bootstraps all registered applications
     *
     * Boots the shared kernel and initializes all registered applications in registration order.
     *
     * Each application retains its independent lifecycle state while sharing the same kernel.
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
    public function bootstrap ():void {

        $this->kernel->boot();

        foreach ($this->applications as $application)
            $application->initialize();

    }

    /**
     * ### Shuts down all managed applications and the shared kernel
     *
     * Disposes all registered applications in reverse registration order and subsequently shuts down the shared kernel.
     *
     * The kernel shutdown is attempted even if application disposal fails.
     *
     * Application disposal must be complete before the kernel is shut down because applications may depend on kernel
     * services during their disposal lifecycle.
     * @since 1.0.0
     *
     * @uses \FireHub\Application\Application::dispose() To dispose the application.
     * @uses \FireHub\Runtime\Arr\Transform::reverse() To reverse the application registration order.
     * @uses \FireHub\Kernel\Kernel::shutdown() To shutdown the kernel.
     *
     * @throws \FireHub\Kernel\Exception\InvalidKernelStateException If shutdown is not allowed.
     * @throws \FireHub\Kernel\Exception\KernelShutdownException If termination fails.
     * @throws \FireHub\Application\Exception\InvalidApplicationStateException If disposal is not allowed.
     * @throws \FireHub\Application\Exception\ApplicationDisposalException If a disposer fails.
     *
     * @return void
     */
    public function shutdown ():void {

        try {

            foreach (Runtime\Arr\Transform::reverse($this->applications) as $application)
                $application->dispose();

            } finally {

                $this->kernel->shutdown();

            }

        }

}