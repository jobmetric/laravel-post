<?php

namespace JobMetric\Post\Exceptions;

use RuntimeException;

/** Indicate that a post status change is not allowed by its workflow. */
class PostStatusTransitionNotAllowedException extends RuntimeException
{
}
