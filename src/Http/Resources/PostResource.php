<?php

namespace JobMetric\Post\Http\Resources;

use DateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JobMetric\Post\Enums\PostStatusEnum;
use JobMetric\Translation\Models\Translation;

/**
 * @property mixed $id
 * @property string $type
 * @property bool $comment_status
 * @property string $password
 * @property PostStatusEnum $status
 * @property DateTime $published_at
 * @property DateTime $created_at
 * @property DateTime $updated_at
 * @property DateTime $deleted_at
 *
 * @property Translation[] translations
 * @property TaxonomyRelation[] taxonomyRelations
 * @property TaxonomyPath[] paths
 * @property Taxonomy[] children
 */
class PostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        //TODO replace this with a helper
        global $translationLocale;

        // $taxonomyTypes = getTaxonomyType();

        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,

            'translations' => translationResourceData($this->translations, $translationLocale),

            // 'taxonomyRelations' => $this->whenLoaded('taxonomyRelations', function () {
            //     return TaxonomyRelationResource::collection($this->taxonomyRelations);
            // }),

            // 'paths' => $this->whenLoaded('paths', function () {
            //     return TaxonomyPathResource::collection($this->paths);
            // }),

            // 'children_count' => $this->whenLoaded('children', function () {
            //     return count($this->children);
            // }),
        ];
    }
}
