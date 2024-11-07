<?php

namespace JobMetric\Post\Enums;

use JobMetric\PackageCore\Enums\EnumToArray;

/**
 * @method static PUBLISH()
 * @method static FUTURE()
 * @method static DRAFT()
 * @method static PENDING()
 * @method static PRIVATE()
 * @method static ARCHIVE()
 * @method static DISABLED()
 */
enum PostStatusEnum: string
{
    use EnumToArray;

    case PUBLISH = "publish";
    case FUTURE = "future";
    case DRAFT = "draft";
    case PENDING = "pending";
    case PRIVATE = "private";
    case ARCHIVE = "archive";
    case DISABLED = "disabled";
}
