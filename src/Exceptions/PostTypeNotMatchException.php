<?php

namespace JobMetric\Post\Exceptions;

use Exception;
use Throwable;

class PostTypeNotMatchException extends Exception
{
    public function __construct(string $type, int $code = 404, ?Throwable $previous = null)
    {
        parent::__construct(trans('post::base.exceptions.post_type_not_match', [
            'type' => $type,
        ]), $code, $previous);
    }
}
