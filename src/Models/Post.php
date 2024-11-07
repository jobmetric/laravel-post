<?php

namespace JobMetric\Post\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use JobMetric\Post\Enums\PostStatusEnum;
use JobMetric\Translation\Contracts\TranslationContract;
use JobMetric\Translation\HasTranslation;
use JobMetric\Translation\TranslatableWithType;

/**
 * JobMetric\Category\Models\Post
 *
 * @property int $id
 * @property int $type
 * @property int $parent_id
 * @property int $ordering
 * @property int $status
 *
 * @method static find(int $id)
 */
class Post extends Model implements TranslationContract
{
    use HasFactory,
        HasTranslation,
        TranslatableWithType;

    protected $fillable = [
        'type',
        'comment_status',
        'password',
        'status',
        'published_at'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'type' => 'string',
        'comment_status' => 'boolean',
        'status' => PostStatusEnum::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];


    /**
     * The "booted" method of the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::retrieved(function (Post $category) {
            $category->init();
        });

        static::creating(function (Post $category) {
            $category->init();
        });
    }

    /**
     * the "init" method collect translation fields from the listeners and push them into $transType array to using in hasTranslation trait later
     * @return void
     */
    public function init(): void
    {
        $postTypes = getPostTypes();

        foreach ($postTypes as $type => $postType) {
            // Set the translation for the category type.
            $this->setTrans($type, $postType['translation']['fields']);

            if (isset($postType['translation']['seo']) && $postType['translation']['seo']) {
                $this->setSeoTransFields($type);
            }

            // Set the metadata for the category type.
            $this->setMeta($type, $postType['metadata']);

            // Set the media collection for the category type.
            $this->setMediaCollection($type, $postType['media']);
        }
    }
}
