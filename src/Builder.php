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
use FireHub\Application\Exception\ContainerRegistrationNotSupportedException;

/**
 * ### Builds and configures FireHub applications
 *
 * Provides a fluent API for assembling an application from its context, kernel, dependency injection container, and
 * lifecycle components.
 *
 * The builder separates application construction from runtime lifecycle management. Building an application does not
 * boot its kernel, initialize the application, or execute any operations.
 *
 * A kernel and container are created automatically when no explicit instances are provided.
 *
 * Each build operation creates a new application instance. Explicitly configured kernel and container instances are
 * shared between builds, while default instances are created independently for each application.
 *
 * <code>
 * use FireHub\Application\Builder;
 * use FireHub\Application\Context;
 * use FireHub\Application\Context\Name;
 * use FireHub\Application\Context\Environment;
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
 *     ->withInitializer(new ClassThatImplementsInitializer)
 *     ->withDisposer(new ClassThatImplementsDisposer)
 *     ->build();
 *
 * $kernel->boot();
 * $app->initialize();
 *
 * try {
 *
 *     $app->execute(function (Application $app):void {
 *
 *         $service = $app->container()->get(MyService::class);
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
     * ### Configures the application kernel
     *
     * Sets the kernel associated with applications created by this builder.
     *
     * The specified kernel may be shared between multiple applications.
     * @since 1.0.0
     *
     * @param \FireHub\Kernel\Kernel $kernel <p>
     * Application kernel.
     * </p>
     *
     * @return $this Current builder.
     */
    public function withKernel (Kernel $kernel):self {

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
     * Module services are registered in dependency order before the application is constructed.
     *
     * When the builder creates its own container, the container is automatically frozen after service registration.
     *
     * Externally supplied containers remain under the caller's control and are never frozen by the builder.
     *
     * Building an application does not initialize its lifecycle.
     * @since 1.0.0
     *
     * @uses \FireHub\Module\Manager::ordered() To get the ordered module list.
     * @uses \FireHub\Core\Boundary\Module\RegistersServices::register() To register services.
     * @uses \FireHub\Container\Container::freeze() To freeze the container.
     *
     * @throws \FireHub\Application\Exception\InvalidApplicationStateException If registration is not allowed.
     * @throws \FireHub\Module\Exception\ModuleDependencyNotFoundException If a required module is not registered.
     * @throws \FireHub\Module\Exception\CircularModuleDependencyException If circular dependencies are detected.
     * @throws \FireHub\Application\Exception\ContainerRegistrationNotSupportedException If the container does not
     * support service registration.
     *
     * @return \FireHub\Application\Application Configured application.
     */
    public function build ():Application {

        $container = $this->container ?? new Container;

        if ($this->modules !== null) {

            foreach ($this->modules->ordered() as $module) {

                if (!$module instanceof RegistersServices)
                    continue;

                if (!$container instanceof Registrar)
                    throw new ContainerRegistrationNotSupportedException(
                        'The configured container does not support service registration.'
                    );

                $module->register($container);

            }

        }

        if ($this->container === null && $container instanceof Container)
            $container->freeze();

        $application = new Application(
            $this->context,
            $this->kernel ?? new Kernel,
            $container
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

}