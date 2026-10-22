<?php declare(strict_types = 1);

/**
 * This file is part of the FireHub Project ecosystem
 *
 * @author Danijel Galić <danijel.galic@outlook.com>
 * @copyright 2026-present The FireHub Project - All rights reserved
 * @license https://opensource.org/license/Apache-2-0 Apache License, Version 2.0
 *
 * @php-version >=7.4
 * @package Application
 */

namespace FireHub\Tests\Application\Integration;

use FireHub\Testing\FireHubTestCase;
use FireHub\Application\ {
    Application, Context, Disposer, Initializer, Manager, State
};
use FireHub\Application\Context\ {
    Environment, Name
};
use FireHub\Container\Container;
use FireHub\Kernel\ {
    Kernel, Terminator
};
use FireHub\Application\Exception\ {
    ApplicationDisposalException, ApplicationInitializationException
};
use FireHub\Tests\Application\Stubs\LifecycleEvents;
use PHPUnit\Framework\Attributes\ {
    CoversClass, Group, Medium
};
use RuntimeException;

/**
 * ### Tests application manager shutdown integration
 * @since 1.0.0
 */
#[Medium]
#[Group('application')]
#[CoversClass(Manager::class)]
final class ManagerShutdownTest extends FireHubTestCase {

    /**
     * ### Creates an application context
     * @since 1.0.0
     *
     * @return void
     */
    private function context (string $name):Context {

        return new Context(
            new Name($name),
            Environment::TESTING
        );

    }

    /**
     * ### Applications shut down in reverse registration order
     * @since 1.0.0
     *
     * @return void
     */
    public function testShutdownUsesReverseApplicationOrder ():void {

        $events = new LifecycleEvents;

        $manager = new Manager;

        foreach (['frontend', 'backend', 'worker'] as $id) {

            $manager->application($id, $this->context($id))
                ->withDisposer(
                    new class($events, $id) implements Disposer {

                        public function __construct (
                            private LifecycleEvents $events,
                            private string $id
                        ) {}

                        public function dispose (Application $application):void {

                            $this->events->entries[] = $this->id;

                        }

                    }
                )
                ->register();

        }

        $manager->bootstrap();
        $manager->shutdown();

        self::assertSame(
            ['worker', 'backend', 'frontend'],
            $events->entries
        );

    }

    /**
     * ### Kernel termination occurs after application disposal
     * @since 1.0.0
     *
     * @return void
     */
    public function testKernelTerminatesAfterApplications ():void {

        $events = new LifecycleEvents;

        $kernel = new Kernel;

        $kernel->registerTerminator(
            new class($events) implements Terminator {

                public function __construct (
                    private LifecycleEvents $events
                ) {}

                public function terminate ():void {

                    $this->events->entries[] = 'kernel';

                }

            }
        );

        $manager = new Manager($kernel);

        $manager->application('frontend', $this->context('frontend'))
            ->withDisposer(
                new class($events) implements Disposer {

                    public function __construct (
                        private LifecycleEvents $events
                    ) {}

                    public function dispose (Application $application):void {

                        $this->events->entries[] = 'frontend';

                    }

                }
            )
            ->register();

        $manager->bootstrap();
        $manager->shutdown();

        self::assertSame(
            ['frontend', 'kernel'],
            $events->entries
        );

    }

    /**
     * ### Shutdown continues after an application disposal failure
     * @since 1.0.0
     *
     * @return void
     */
    public function testShutdownContinuesAfterDisposalFailure ():void {

        $events = new LifecycleEvents;

        $kernel = new Kernel;

        $kernel->registerTerminator(
            new class($events) implements Terminator {

                public function __construct (
                    private LifecycleEvents $events
                ) {}

                public function terminate ():void {

                    $this->events->entries[] = 'kernel';

                }

            }
        );

        $manager = new Manager($kernel);

        $manager->application('frontend', $this->context('frontend'))
            ->withDisposer(
                new class($events) implements Disposer {

                    public function __construct (
                        private LifecycleEvents $events
                    ) {}

                    public function dispose (Application $application):void {

                        $this->events->entries[] = 'frontend';

                    }

                }
            )
            ->register();

        $manager->application('backend', $this->context('backend'))
            ->withDisposer(
                new class($events) implements Disposer {

                    public function __construct (
                        private LifecycleEvents $events
                    ) {}

                    public function dispose (Application $application):void {

                        $this->events->entries[] = 'backend';

                        throw new RuntimeException('Backend disposal failed.');

                    }

                }
            )
            ->register();

        $manager->bootstrap();

        try {

            $manager->shutdown();

            self::fail('Expected shutdown to fail.');

        } catch (ApplicationDisposalException) {

            self::assertSame(
                ['backend', 'frontend', 'kernel'],
                $events->entries
            );

            self::assertSame(
                State::DISPOSAL_FAILED,
                $manager->get('backend')->state()
            );

            self::assertSame(
                State::DISPOSED,
                $manager->get('frontend')->state()
            );

        }

    }

    /**
     * ### Shared external containers survive manager shutdown
     * @since 1.0.0
     *
     * @return void
     */
    public function testSharedExternalContainerRemainsOpen ():void {

        $container = new Container;

        $manager = new Manager;

        $frontend = $manager->application(
            'frontend',
            $this->context('frontend')
        )->withContainer($container)->register();

        $backend = $manager->application(
            'backend',
            $this->context('backend')
        )->withContainer($container)->register();

        self::assertSame($container, $frontend->container());
        self::assertSame($container, $backend->container());

        $manager->bootstrap();
        $manager->shutdown();

        self::assertFalse($container->isClosed());

        $container->close();

    }

    /**
     * ### Manager bootstrap preserves external container mutability
     * @since 1.0.0
     *
     * @return void
     */
    public function testBootstrapDoesNotFreezeExternalContainer ():void {

        $container = new Container;

        $manager = new Manager;

        $manager->application(
            'frontend',
            $this->context('frontend')
        )->withContainer($container)->register();

        $manager->bootstrap();

        self::assertFalse($container->isFrozen());

        $manager->shutdown();

        $container->close();

    }

    /**
     * ### Manager shutdown closes owned application containers
     * @since 1.0.0
     */
    public function testShutdownClosesOwnedContainers ():void {

        $manager = new Manager;

        $frontend = $manager->application(
            'frontend',
            $this->context('frontend')
        )->register();

        $backend = $manager->application(
            'backend',
            $this->context('backend')
        )->register();

        $manager->bootstrap();
        $manager->shutdown();

        $frontendContainer = $frontend->container();
        $backendContainer = $backend->container();

        self::assertInstanceOf(Container::class, $frontendContainer);
        self::assertInstanceOf(Container::class, $backendContainer);

        self::assertTrue($frontendContainer->isClosed());
        self::assertTrue($backendContainer->isClosed());

        self::assertNotSame(
            $frontendContainer,
            $backendContainer
        );

    }

    /**
     * ### Shuts down all applications after partial bootstrap failure
     * @since 1.0.0
     *
     * @return void
     */
    public function testShutdownAfterPartialBootstrapFailure ():void {

        $events = new LifecycleEvents;

        $kernel = new Kernel;

        $kernel->registerTerminator(
            new class($events) implements Terminator {

                public function __construct (
                    private LifecycleEvents $events
                ) {}

                public function terminate ():void {

                    $this->events->entries[] = 'kernel';

                }

            }
        );

        $manager = new Manager($kernel);

        $frontend = $manager->application(
            'frontend',
            $this->context('frontend')
        )->withInitializer(
            new class($events) implements Initializer {

                public function __construct (
                    private LifecycleEvents $events
                ) {}

                public function initialize (Application $application):void {

                    $this->events->entries[] = 'frontend.initialize';

                }

            }
        )->withDisposer(
            new class($events) implements Disposer {

                public function __construct (
                    private LifecycleEvents $events
                ) {}

                public function dispose (Application $application):void {

                    $this->events->entries[] = 'frontend.dispose';

                }

            }
        )->register();

        $backend = $manager->application(
            'backend',
            $this->context('backend')
        )->withInitializer(
            new class($events) implements Initializer {

                public function __construct (
                    private LifecycleEvents $events
                ) {}

                public function initialize (Application $application):void {

                    $this->events->entries[] = 'backend.initialize';

                    throw new RuntimeException('Backend initialization failed.');

                }

            }
        )->withDisposer(
            new class($events) implements Disposer {

                public function __construct (
                    private LifecycleEvents $events
                ) {}

                public function dispose (Application $application):void {

                    $this->events->entries[] = 'backend.dispose';

                }

            }
        )->register();

        $bootstrapFailure = null;

        try {

            $manager->bootstrap();

        } catch (ApplicationInitializationException $exception) {

            $bootstrapFailure = $exception;

        } finally {

            $manager->shutdown();

        }

        self::assertInstanceOf(
            ApplicationInitializationException::class,
            $bootstrapFailure
        );

        self::assertSame(
            [
                'frontend.initialize',
                'backend.initialize',
                'backend.dispose',
                'frontend.dispose',
                'kernel'
            ],
            $events->entries
        );

        self::assertSame(State::DISPOSED, $frontend->state());
        self::assertSame(State::DISPOSED, $backend->state());

        $frontendContainer = $frontend->container();
        $backendContainer = $backend->container();

        self::assertInstanceOf(Container::class, $frontendContainer);
        self::assertInstanceOf(Container::class, $backendContainer);

        self::assertTrue($frontendContainer->isClosed());
        self::assertTrue($backendContainer->isClosed());

    }

}