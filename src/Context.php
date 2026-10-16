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

use FireHub\Core\Type\ValueObject;
use FireHub\Application\Context\ {
    Environment, Name
};

/**
 * ### Represents the application execution context
 *
 * Defines the immutable identity and execution environment of an application.
 *
 * The context contains application-wide metadata and remains unchanged throughout the application lifecycle.
 * @since 1.0.0
 *
 * @extends \FireHub\Core\Type\ValueObject<array{
 *     name: \FireHub\Application\Context\Name<non-empty-string>,
 *     environment: \FireHub\Application\Context\Environment
 * }>
 */
final readonly class Context extends ValueObject {

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @param \FireHub\Application\Context\Name<non-empty-string> $name <p>
     * Application name.
     * </p>
     * @param \FireHub\Application\Context\Environment $environment <p>
     * Application environment.
     * </p>
     *
     * @return void
     */
    public function __construct (
        public Name $name,
        public Environment $environment
    ) {}

    /**
     * @inheritDoc
     *
     * @since 1.0.0
     */
    public function value ():array {

        return [
            'name' => $this->name,
            'environment' => $this->environment
        ];

    }

    /**
     * @inheritDoc
     *
     * @since 1.0.0
     *
     * @uses \FireHub\Application\Context\Name::value() To get the name value.
     *
     * @return array{
     *     name: non-empty-string,
     *     environment: \FireHub\Application\Context\Environment
     * } Comparison value.
     */
    protected function comparisonValue ():array {

        return [
            'name' => $this->name->value(),
            'environment' => $this->environment
        ];

    }

}