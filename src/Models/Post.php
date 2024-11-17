<?php

namespace JobMetric\Post\Models;

use DateTime;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use JobMetric\Media\Contracts\MediaContract;
use JobMetric\Media\MediaableWithType;
use JobMetric\Metadata\Contracts\MetaContract;
use JobMetric\Metadata\HasMeta;
use JobMetric\Metadata\MetaableWithType;
use JobMetric\Post\Enums\PostStatusEnum;
use JobMetric\Taxonomy\Contracts\TaxonomyContract;
use JobMetric\Taxonomy\HasTaxonomy;
use JobMetric\Translation\Contracts\TranslationContract;
use JobMetric\Translation\HasTranslation;
use JobMetric\Translation\TranslatableWithType;

/**
 * JobMetric\Category\Models\Post
 *
 * @property int $id
 * @property string $type
 * @property bool $comment_status
 * @property string $password
 * @property PostStatusEnum $status
 * @property DateTime $published_at
 *
 * @method static find(int $id)
 */
class Post extends Model implements TranslationContract, MetaContract, MediaContract, TaxonomyContract
{
    use HasFactory,
        HasTranslation,
        TranslatableWithType,
        HasMeta,
        MetaableWithType,
        MediaableWithType,
        HasTaxonomy;

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
        'password' => 'string',
        'status' => PostStatusEnum::class,
        'published_at' => 'datetime',
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

    /**
     * taxonomy allows the type.
     *
     * @return array
     */
    public function taxonomyAllowTypes(): array
    {
        return [
            'page' => [
                'type' => 'page_taxonomy',
                'multiple' => true
            ]
        ];
    }

}
