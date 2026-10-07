<?php

namespace JobMetric\Post\Contracts;

interface PostContract
{
    /** @return array<string, array{type: string, multiple?: bool}> */
    public function taxonomyAllowTypes(): array;
}
