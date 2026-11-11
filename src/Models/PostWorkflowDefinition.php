<?php

namespace JobMetric\Post\Models;

use Illuminate\Database\Eloquent\Model;

/** Retain an independently versioned workflow draft and its compiled flow identifier. */
class PostWorkflowDefinition extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['graph' => 'array', 'enabled' => 'boolean', 'version' => 'integer'];

    /**
     * Resolve the configurable workflow definition table.
     *
     * @return string
     */
    public function getTable(): string
    {
        return config('post.tables.workflow_definition', 'post_workflow_definitions');
    }
}
