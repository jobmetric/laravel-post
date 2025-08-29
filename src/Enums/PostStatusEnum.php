<?php

namespace JobMetric\Post\Enums;

use JobMetric\PackageCore\Enums\EnumMacros;

/**
 * @method static string PUBLISH()
 * @method static string FUTURE()
 * @method static string DRAFT()
 * @method static string PENDING()
 * @method static string PRIVATE()
 * @method static string ARCHIVE()
 * @method static string DISABLED()
 */
enum PostStatusEnum: string
{
    use EnumMacros;

    case PUBLISH = "publish";
    case FUTURE = "future";
    case DRAFT = "draft";
    case PENDING = "pending";
    case PRIVATE = "private";
    case ARCHIVE = "archive";
    case DISABLED = "disabled";
}
