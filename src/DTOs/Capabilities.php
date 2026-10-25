<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\DTOs;

use IvanVasechko\Oukitel\Enums\TslCode;

/** @experimental Inferred from reported codes, not a device-model safety guarantee. */
final readonly class Capabilities
{
    /**
     * @experimental
     *
     * @param  list<TslCode>  $readable
     * @param  list<TslCode>  $controllable
     */
    public function __construct(public array $readable, public array $controllable) {}

    /**
     * @experimental
     *
     * @param  list<TslAttribute>  $attributes
     */
    public static function fromAttributes(array $attributes): self
    {
        $readable = [];
        $controllable = [];
        foreach ($attributes as $attribute) {
            $code = TslCode::tryFrom($attribute->resourceCode);
            if ($code !== null) {
                $readable[$code->value] = $code;
                if ($code->isControllable()) {
                    $controllable[$code->value] = $code;
                }
            }
        }

        return new self(array_values($readable), array_values($controllable));
    }

    /** @experimental */
    public function canRead(TslCode $code): bool
    {
        return in_array($code, $this->readable, true);
    }

    /** @experimental */
    public function canControl(TslCode $code): bool
    {
        return in_array($code, $this->controllable, true);
    }
}
