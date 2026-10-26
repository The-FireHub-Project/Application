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

use FireHub\Core\Boundary\ {
    Container\Registrar, Container as ContainerBoundary
};
use FireHub\Core\Boundary\ {
    Module\RegistersServices, Module
};
use FireHub\Container\Container;
use FireHub\Kernel\Kernel;
use FireHub\Module\Manager as ModuleManager;
use FireHub\Application\Exception\{
    ApplicationAlreadyRegisteredException, ApplicationKernelMismatchException,
    ContainerRegistrationNotSupportedException, InvalidApplicationIdentifierException, UnmanagedApplicationBuilderException
};

/**
 * ### Builds and configures FireHub applications
 *
 * Provides a fluent API for assembling applications from their context, kernel, dependency injection container,
 * modules, and lifecycle components.
 *
 * The builder supports both standalone and managed application construction. Standalone builders are created using
 * create(), while managed builders are associated with an application manager and registration identifier.
 *
 * Building an application does not boot its kernel, initialize the application, or execute any operations.
 * Managed builders additionally support registering constructed applications with their associated manager.
 *
 * A kernel and container are created automatically when no explicit instances are provided. Managed applications
 * inherit the shared kernel of their application manager.
 *
 * Each build operation creates a new application instance. Explicitly configured kernel and container instances
 * are shared between builds, while default instances are created independently for standalone applications.
 *
 * <code>
 * use FireHub\Application\Application;
 * use FireHub\Application\Builder;
 * use FireHub\Application\Context;
 * use FireHub\Application\Context\Name;
 * use FireHub\Application\Context\Environment;
 * use FireHub\Application\ExecutionContext;
 * use FireHub\Container\Container;
 * use FireHub\Kernel\Kernel;
 *
 * $kernel = new Kernel;
 * $container = new Container;
 *
 * $container->singleton(MyService::class);
 * $container->freeze();
 *
 * $app = Builder::create(
 *     new Context(
 *         new Name('FireHub'),
 *         Environment::PRODUCTION
 *     ))->withKernel($kernel)
 *     ->withContainer($container)
 *     ->withModule(new AppModule)
 *     ->withInitializer(new AppInitializer)
 *     ->withDisposer(new AppDisposer)
 *     ->build();
 *
 * $kernel->boot();
 * $app->initialize();
 *
 * try {
 *
 *     $app->execute(function (ExecutionContext $execution):void {
 *
 *         $service = $execution->scope()->get(MyService::class);
 *
 *         $service->run();
 *
 *     });
 *
 * } finally {
 *
 *     try {
 *
 *         $app->dispose();
 *
 *     } finally {
 *
 *         $kernel->shutdown();
 *
 *     }
 *
 * }
 * </code>
 *
 * @since 1.0.0
 */
final class Builder {

    /**
     * ### Application manager
     * @since 1.0.0
     */
    private ?Manager $manager = null;

    /**
     * ### Application registration identifier
     * @since 1.0.0
     *
     * @var null|non-empty-string
     */
    private ?string $registration_id = null;

    /**
     * ### Application kernel
     * @since 1.0.0
     */
    private ?Kernel $kernel = null;

    /**
     * ### Application dependency injection container
     * @since 1.0.0
     */
    private ?ContainerBoundary $container = null;

    /**
     * ### Application module manager
     * @since 1.0.0
     */
    private ?ModuleManager $modules = null;

    /**
     * ### Application initializer
     * @since 1.0.0
     *
     * @var list<array{initializer: \FireHub\Application\Initializer, order: int}>
     */
    private array $initializers = [];

    /**
     * ### Application disposers
     * @since 1.0.0
     *
     * @var list<array{disposer: \FireHub\Application\Disposer, order: int}>
     */
    private array $disposers = [];

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
    private function __construct (
        private readonly Context $context
    ) {}

    /**
     * ### Creates an application builder
     *
     * Creates a new builder configured with the specified application context.
     * @since 1.0.0
     *
     * @param \FireHub\Application\Context $context <p>
     * Application context.
     * </p>
     *
     * @return self New application builder.
     */
    public static function create (Context $context):self {

        return new self($context);

    }

    /**
     * ### Creates a managed application builder
     *
     * Creates an application builder associated with the specified manager and registration identifier.
     *
     * Managed applications inherit the manager's shared kernel.
     * @since 1.0.0
     *
     * @uses \FireHub\Application\Manager::kernel() To get the application kernel.
     *
     * @param \FireHub\Application\Context $context <p>
     * Application context.
     * </p>
     * @param \FireHub\Application\Manager $manager <p>
     * Application manager.
     * </p>
     * @param non-empty-string $id <p>
     * Application registration identifier.
     * </p>
     *
     * @throws \FireHub\Application\Exception\InvalidApplicationIdentifierException If the identifier is empty.
     *
     * @return self Managed application builder.
     */
    public static function managed (Context $context, Manager $manager, string $id):self {

        if ($id === '')
            throw new InvalidApplicationIdentifierException(
                'Application identifier cannot be empty.'
            );

        $builder = new self($context);

        $builder->manager = $manager;
        $builder->registration_id = $id;
        $builder->kernel = $manager->kernel();

        return $builder;

    }

    /**
     * ### Configures the application kernel
     *
     * Sets the kernel associated with applications created by this builder.
     *
     * The specified kernel may be shared between multiple applications.
     * @since 1.0.0
     *
     * @uses \FireHub\Application\Manager::kernel() To get the application kernel.
     *
     * @param \FireHub\Kernel\Kernel $kernel <p>
     * Application kernel.
     * </p>
     *
     * @throws \FireHub\Application\Exception\ApplicationKernelMismatchException If the kernel is not shared with the
     * manager.
     *
     * @return $this Current builder.
     */
    public function withKernel (Kernel $kernel):self {

        if ($this->manager !== null && $kernel !== $this->manager->kernel())
            throw new ApplicationKernelMismatchException;

        $this->kernel = $kernel;

        return $this;

    }

    /**
     * ### Configures the dependency injection container
     *
     * Sets the container associated with applications created by this builder.
     *
     * The container is expected to be configured before application initialization. The builder does not register
     * services, freeze registrations, or assume ownership of the container lifecycle.
     * @since 1.0.0
     *
     * @param \FireHub\Core\Boundary\Container $container <p>
     * Application dependency injection container.
     * </p>
     *
     * @return $this Current builder.
     */
    public function withContainer (ContainerBoundary $container):self {

        $this->container = $container;

        return $this;

    }

    /**
     * ### Registers an application module
     *
     * Adds a module to the application composition.
     *
     * Modules are identified by their fully qualified class names. Module dependencies are resolved during
     * application construction.
     *
     * Service registrations contributed by modules are applied during the build process, before application
     * initialization.
     * @since 1.0.0
     *
     * @uses \FireHub\Module\Manager::register() To register the module.
     *
     * @param \FireHub\Core\Boundary\Module $module <p>
     * Application module.
     * </p>
     *
     * @throws \FireHub\Module\Exception\ModuleAlreadyRegisteredException If the module is already registered.
     * @throws \FireHub\Module\Exception\ModuleManagerFrozenException If the manager is frozen.
     *
     * @return $this Current builder.
     */
    public function withModule (Module $module):self {

        $this->modules ??= new ModuleManager;

        $this->modules->register($module);

        return $this;

    }

    /**
     * ### Registers an application initializer
     *
     * Adds an initializer to the application startup lifecycle.
     *
     * Initializers are registered during application construction and executed when the application is initialized.
     * @since 1.0.0
     *
     * @param \FireHub\Application\Initializer $initializer <p>
     * Initializer to register.
     * </p>
     * @param int $order [optional] <p>
     * Initializer execution order.
     * </p>
     *
     * @return $this Current builder.
     */
    public function withInitializer (Initializer $initializer, int $order = 0):self {

        $this->initializers[] = [
            'initializer' => $initializer,
            'order' => $order
        ];

        return $this;

    }

    /**
     * ### Registers an application disposer
     *
     * Adds a disposer to the application shutdown lifecycle.
     *
     * Disposers are registered during application construction and executed when the application has been disposed.
     * @since 1.0.0
     *
     * @param Disposer $disposer <p>
     * Disposer to register.
     * </p>
     * @param int $order [optional] <p>
     * Disposer execution order.
     * </p>
     *
     * @return $this Current builder.
     */
    public function withDisposer (Disposer $disposer, int $order = 0):self {

        $this->disposers[] = [
            'disposer' => $disposer,
            'order' => $order
        ];

        return $this;

    }

    /**
     * ### Builds an application
     *
     * Creates an application using the configured context, kernel, dependency injection container, modules,
     * initializers, and disposers.
     *
     * Modules are resolved in dependency order, and their service registrations are applied before the application
     * is constructed.
     *
     * The module manager is cloned and frozen after successful service registration, preventing further changes
     * to the application's module composition.
     *
     * Containers created by the builder are automatically frozen after service registration. Externally supplied
     * containers remain under the caller's control and are never frozen by the builder.
     *
     * Building an application does not initialize its lifecycle.
     * @since 1.0.0
     *
     * @uses \FireHub\Module\Manager::ordered() To get the ordered module list.
     * @uses \FireHub\Module\Manager::hasServiceRegistrations() To check if the module manager has service
     * registrations.
     * @uses \FireHub\Module\Manager::registerServices() To register services in the container.
     * @uses \FireHub\Module\Manager::freeze() To freeze the module registry.
     * @uses \FireHub\Container\Container::freeze() To freeze the container.
     * @uses \FireHub\Application\Application::registerInitializer() To register application initializers.
     * @uses \FireHub\Application\Application::registerDisposer() To register application disposers.
     *
     * @throws \FireHub\Application\Exception\InvalidApplicationStateException If registration is not allowed.
     * @throws \FireHub\Module\Exception\ModuleDependencyNotFoundException If a required module is not registered.
     * @throws \FireHub\Module\Exception\CircularModuleDependencyException If circular dependencies are detected.
     * @throws \FireHub\Application\Exception\ContainerRegistrationNotSupportedException If the container does not
     * support service registration.
     * @throws \FireHub\Container\Exception\ContainerClosedException If the container is closed.
     *
     * @return \FireHub\Application\Application Configured application.
     */
    public function build ():Application {

        $owns_container = $this->container === null;

        $container = $this->container ?? new Container;

        $modules = clone ($this->modules ?? new ModuleManager);

        if ($modules->hasServiceRegistrations()) {

            if (!$container instanceof Registrar)
                throw new ContainerRegistrationNotSupportedException(
                    'The configured container does not support service registration.'
                );

            $modules->registerServices($container);

        } else {

            $modules->ordered();

        }

        $modules->freeze();

        if ($this->container === null && $container instanceof Container)
            $container->freeze();

        $application = new Application(
            $this->context,
            $this->kernel ?? new Kernel,
            $container,
            $modules,
            $owns_container
        );

        foreach ($this->initializers as $entry)
            $application->registerInitializer(
                $entry['initializer'],
                $entry['order']
            );

        foreach ($this->disposers as $entry)
            $application->registerDisposer(
                $entry['disposer'],
                $entry['order']
            );

        return $application;

    }

    /**
     * ### Builds and registers an application
     *
     * Constructs the application and registers it with the associated application manager.
     *
     * Registration is available only for builders created through Builder::managed().
     * @since 1.0.0
     *
     * @uses \FireHub\Application\Builder::build() To construct the application.
     * @uses \FireHub\Application\Manager::register() To register the application.
     * @uses \FireHub\Application\Manager::has() To check if the application is already registered.
     *
     * @throws \FireHub\Application\Exception\UnmanagedApplicationBuilderException If the builder is not associated
     * with a manager.
     * @throws \FireHub\Application\Exception\ApplicationAlreadyRegisteredException If the identifier is already
     * registered.
     * @throws \FireHub\Application\Exception\InvalidApplicationStateException If registration is not allowed.
     * @throws \FireHub\Module\Exception\ModuleDependencyNotFoundException If a required module is not registered.
     * @throws \FireHub\Module\Exception\CircularModuleDependencyException If circular dependencies are detected.
     * @throws \FireHub\Application\Exception\ContainerRegistrationNotSupportedException If the container does not
     * support service registration.
     * @throws \FireHub\Application\Exception\InvalidApplicationIdentifierException If the identifier is empty.
     * @throws \FireHub\Application\Exception\ApplicationAlreadyRegisteredException If the application is already
     * registered.
     * @throws \FireHub\Application\Exception\ApplicationKernelMismatchException If the application kernel does not
     * match the manager kernel.
     * @throws \FireHub\Container\Exception\ContainerClosedException If the container is closed.
     *
     * @return \FireHub\Application\Application Registered application.
     */
    public function register ():Application {

        if ($this->manager === null || $this->registration_id === null)
            throw new UnmanagedApplicationBuilderException(
                'Cannot register an application using a standalone builder.',
                context: [
                    'manager' => $this->manager,
                    'registration_id' => $this->registration_id,
                ]
            );

        if ($this->manager->has($this->registration_id))
            throw new ApplicationAlreadyRegisteredException;

        $application = $this->build();

        $this->manager->register(
            $this->registration_id,
            $application
        );

        return $application;

    }

}