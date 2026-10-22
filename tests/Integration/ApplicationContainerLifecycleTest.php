<?php declare(strict_types = 1);

/**
 * This file is part of the FireHub Project ecosystem
 *
 * @author Danijel Galić <danijel.galic@outlook.com>
 * @copyright 2026-present The FireHub Project - All rights reserved
 * @license https://opensource.org/license/Apache-2-0 Apache License, Version 2.0
 *
 * @php-version >=7.4
 * @package Application/Tests
 */

namespace FireHub\Tests\Application\Integration;

use FireHub\Testing\FireHubTestCase;
use FireHub\Application\ {
    Application, Builder, Context, Disposer, ExecutionContext, State
};
use FireHub\Application\Context\ {
    Environment, Name
};
use FireHub\Container\Container;
use FireHub\Kernel\Kernel;
use FireHub\Application\Exception\ {
    ApplicationDisposalException, InvalidApplicationStateException
};
use FireHub\Tests\Application\Stubs\ {
    ScopedLifecycleService, SingletonLifecycleService, LifecycleEvents
};
use PHPUnit\Framework\Attributes\ {
    CoversClass, Group, Medium
};
use RuntimeException;

/**
 * ### Tests application and container lifecycle integration
 * @since 1.0.0
 */
#[Medium]
#[Group('application')]
#[CoversClass(Application::class)]
final class ApplicationContainerLifecycleTest extends FireHubTestCase {

    /**
     * ### Creates an application context
     * @since 1.0.0
     *
     * @return \FireHub\Application\Context
     */
    private function context ():Context {

        return new Context(
            new Name('IntegrationTest'),
            Environment::TESTING
        );

    }

    /**
     * ### Application-owned containers are closed during disposal
     * @since 1.0.0
     *
     * @return void
     */
    public function testOwnedContainerClosesOnDispose ():void {

        $application = new Application($this->context());

        $container = $application->container();

        self::assertInstanceOf(Container::class, $container);
        self::assertFalse($container->isClosed());

        $application->dispose();

        self::assertTrue($container->isClosed());
        self::assertSame(State::DISPOSED, $application->state());

    }

    /**
     * ### Externally owned containers remain open
     * @since 1.0.0
     *
     * @return void
     */
    public function testExternalContainerRemainsOpen ():void {

        $container = new Container;

        $application = new Application(
            context: $this->context(),
            container: $container
        );

        $application->dispose();

        self::assertFalse($container->isClosed());
        self::assertSame(State::DISPOSED, $application->state());

        $container->close();

    }

    /**
     * ### The application owns the container created by the builder
     * @since 1.0.0
     *
     * @return void
     */
    public function testBuilderOwnedContainerCloses ():void {

        $application = Builder::create($this->context())->build();

        $container = $application->container();

        self::assertInstanceOf(Container::class, $container);
        self::assertTrue($container->isFrozen());

        $application->dispose();

        self::assertTrue($container->isClosed());

    }

    /**
     * ### Builder preserves external container ownership
     * @since 1.0.0
     *
     * @return void
     */
    public function testBuilderPreservesExternalContainer ():void {

        $container = new Container;

        $application = Builder::create($this->context())
            ->withContainer($container)
            ->build();

        $application->dispose();

        self::assertFalse($container->isClosed());

        $container->close();

    }

    /**
     * ### Scoped resources are disposed after execution
     * @since 1.0.0
     *
     * @return void
     */
    public function testScopedServiceDisposesAfterExecution ():void {

        $events = new LifecycleEvents;

        $container = new Container;

        $container->instance(LifecycleEvents::class, $events);
        $container->scoped(ScopedLifecycleService::class);

        $kernel = new Kernel;

        $application = new Application(
            context: $this->context(),
            kernel: $kernel,
            container: $container,
            owns_container: true
        );

        $kernel->boot();
        $application->initialize();

        $application->execute(
            function (ExecutionContext $execution) use ($events):void {

                $service = $execution->scope()->get(
                    ScopedLifecycleService::class
                );

                self::assertInstanceOf(
                    ScopedLifecycleService::class,
                    $service
                );

                self::assertSame([], $events->entries);

            }
        );

        self::assertSame(['scoped'], $events->entries);

        $application->dispose();
        $kernel->shutdown();

        self::assertSame(['scoped'], $events->entries);

    }

    /**
     * ### Singleton resources survive execution and dispose with the application
     * @since 1.0.0
     *
     * @return void
     */
    public function testSingletonServiceDisposesWithApplication ():void {

        $events = new LifecycleEvents;

        $container = new Container;

        $container->instance(LifecycleEvents::class, $events);
        $container->singleton(SingletonLifecycleService::class);

        $kernel = new Kernel;

        $application = new Application(
            context: $this->context(),
            kernel: $kernel,
            container: $container,
            owns_container: true
        );

        $kernel->boot();
        $application->initialize();

        $application->execute(
            function (ExecutionContext $execution):void {

                $execution->scope()->get(
                    SingletonLifecycleService::class
                );

            }
        );

        self::assertSame([], $events->entries);

        $application->dispose();

        self::assertSame(['singleton'], $events->entries);

        $kernel->shutdown();

    }

    /**
     * ### Application disposers run before singleton disposal
     * @since 1.0.0
     *
     * @return void
     */
    public function testApplicationDisposerRunsBeforeContainerDisposal ():void {

        $events = new LifecycleEvents;

        $container = new Container;

        $container->instance(LifecycleEvents::class, $events);
        $container->singleton(SingletonLifecycleService::class);

        $application = new Application(
            context: $this->context(),
            container: $container,
            owns_container: true
        );

        $container->get(SingletonLifecycleService::class);

        $application->registerDisposer(
            new class($events) implements Disposer {

                public function __construct (
                    private LifecycleEvents $events
                ) {}

                public function dispose (Application $application):void {

                    $this->events->entries[] = 'application';

                }

            }
        );

        $application->dispose();

        self::assertSame(
            ['application', 'singleton'],
            $events->entries
        );

    }

    /**
     * ### Owned containers close even when application disposal fails
     * @since 1.0.0
     *
     * @return void
     */
    public function testContainerClosesWhenDisposerFails ():void {

        $events = new LifecycleEvents;

        $container = new Container;

        $container->instance(LifecycleEvents::class, $events);
        $container->singleton(SingletonLifecycleService::class);

        $application = new Application(
            context: $this->context(),
            container: $container,
            owns_container: true
        );

        $container->get(SingletonLifecycleService::class);

        $application->registerDisposer(
            new class($events) implements Disposer {

                public function __construct (
                    private LifecycleEvents $events
                ) {}

                public function dispose (Application $application):void {

                    $this->events->entries[] = 'disposer';

                    throw new RuntimeException('Disposal failed.');

                }

            }
        );

        try {

            $application->dispose();

            self::fail('Expected application disposal to fail.');

        } catch (ApplicationDisposalException) {

            self::assertTrue($container->isClosed());

            self::assertSame(
                ['disposer', 'singleton'],
                $events->entries
            );

            self::assertSame(
                State::DISPOSAL_FAILED,
                $application->state()
            );

        }

    }

    /**
     * ### Active executions prevent application disposal
     * @since 1.0.0
     *
     * @return void
     */
    public function testCannotDisposeDuringActiveExecution ():void {

        $kernel = new Kernel;

        $application = new Application(
            context: $this->context(),
            kernel: $kernel
        );

        $kernel->boot();
        $application->initialize();

        $container = $application->container();

        $application->execute(
            function () use ($application, $container):void {

                try {

                    $application->dispose();

                    self::fail('Expected disposal to be rejected.');

                } catch (InvalidApplicationStateException) {

                    self::assertSame(
                        State::INITIALIZED,
                        $application->state()
                    );

                    self::assertInstanceOf(Container::class, $container);
                    self::assertFalse($container->isClosed());

                }

            }
        );

        $application->dispose();
        $kernel->shutdown();

        self::assertSame(State::DISPOSED, $application->state());

    }

    /**
     * ### Successful application disposal is idempotent
     * @since 1.0.0
     *
     * @return void
     */
    public function testDisposeIsIdempotent ():void {

        $events = new LifecycleEvents;

        $application = new Application($this->context());

        $application->registerDisposer(
            new class($events) implements Disposer {

                public function __construct (
                    private LifecycleEvents $events
                ) {}

                public function dispose (Application $application):void {

                    $this->events->entries[] = 'dispose';

                }

            }
        );

        $application->dispose();
        $application->dispose();

        self::assertSame(['dispose'], $events->entries);

    }

}