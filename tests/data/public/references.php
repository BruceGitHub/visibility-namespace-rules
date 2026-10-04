<?php

declare(strict_types=1);

namespace App\Api;

use App\Internal\Base;
use App\Internal\Contract;
use App\Internal\Helper;
use App\Internal\Marker;
use App\Internal\SomeTrait;

#[Marker]
final class Service extends Base implements Contract
{
    use SomeTrait;

    public function __construct(private Helper $helper)
    {
    }

    public function run(Base|Helper $input): ?Contract
    {
        if ($input instanceof Helper) {
            return new Contract();
        }

        try {
            return Helper::make();
        } catch (\App\Internal\Failure $failure) {
            throw $failure;
        }
    }
}
