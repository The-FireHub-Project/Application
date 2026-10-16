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

namespace FireHub\Application\Context;

use FireHub\Core\Type\Str\Encoding;
use FireHub\Foundation\Identifier\Name as IdentifierName;
use FireHub\Foundation\Identifier\Exception\InvalidIdentifierValueException;
use FireHub\Runtime;

/**
 * ### Represents an application name
 *
 * Defines an immutable, strongly typed name used to identify an application within the FireHub ecosystem.
 *
 * The application name inherits its fundamental validation rules and value semantics from the Foundation identifier
 * name implementation.
 *
 * Application names are compared by their concrete type and underlying value, ensuring that they remain distinct
 * from other identifier types.
 * @since 1.0.0
 *
 * @template TValue of non-empty-string
 *
 * @extends \FireHub\Foundation\Identifier\Name<TValue>
 */
final readonly class Name extends IdentifierName {

    /**
     * ### Constructor
     * @since 1.0.0
     *
     * @uses \FireHub\Core\Type\Identifier::guard() To enforce the invariant that an identifier name cannot be empty.
     *
     * @param TValue $value <p>
     * The identifier name.
     * </p>
     *
     * @throws \FireHub\Foundation\Identifier\Exception\InvalidIdentifierValueException If the identifier name is
     * empty or does not meet the length requirements.
     * @throws \FireHub\Core\Exception\FireHubException If the condition is not met.
     * @throws \FireHub\Core\Type\Exception\ValueObjectException If the exception is not a FireHubException.
     *
     * @return void
     */
    public function __construct (string $value) {

        parent::__construct($value);

        $length = Runtime\Str\MB\Inspection::length($value, Encoding::UTF_8);

        $this->guard(
            fn() => $length >= 3 && $length <= 64,
            fn() => new InvalidIdentifierValueException(
                'Application name must contain between 3 and 64 characters.'
            )
        );

    }

}