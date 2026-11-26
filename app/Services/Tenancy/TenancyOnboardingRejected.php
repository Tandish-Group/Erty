<?php

namespace App\Services\Tenancy;

use RuntimeException;

/** A tenancy that cannot be created as requested (unit taken, setup not confirmed, ...): the message is shown as is. */
class TenancyOnboardingRejected extends RuntimeException
{
}
