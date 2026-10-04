<?php

declare(strict_types=1);

namespace App\Api;

use App\Internal\Foo;

new Foo();
new \App\Internal\Bar();
\App\Internal\Foo::create();
