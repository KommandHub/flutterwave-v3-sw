<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Checkout\Payment\Struct;

use Shopware\Core\Framework\Struct\Struct;

class FlutterwaveInitializationResponse extends Struct
{
    public function __construct(
        protected string $link
    ) {
    }

    public function getLink(): string
    {
        return $this->link;
    }
}
