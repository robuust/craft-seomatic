<?php
/**
 * SEOmatic plugin for Craft CMS
 *
 * A turnkey SEO implementation for Craft CMS that is comprehensive, powerful,
 * and flexible
 *
 * @link      https://nystudio107.com
 * @copyright Copyright (c) 2017 nystudio107
 */

namespace nystudio107\seomatic\helpers;

use Craft;
use craft\helpers\StringHelper;
use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Asset\Exceptions\AssetTransformException;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\ElementCollection;
use CraftCms\Cms\Element\Queries\ElementQuery;
use CraftCms\Cms\Image\Data\ImageTransform as ImageTransformModel;
use CraftCms\Cms\Shared\Exceptions\NotSupportedException;
use DateTime;
use League\Flysystem\FilesystemException;
use yii\base\InvalidConfigException;
use function in_array;
use function is_array;

/**
 * @author    nystudio107
 * @package   Seomatic
 * @since     3.0.0
 */
class ImageTransform
{
    // Constants
    // =========================================================================

    public const SOCIAL_TRANSFORM_QUALITY = 82;

    public const ALLOWED_SOCIAL_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    public const DEFAULT_SOCIAL_FORMAT = 'jpg';

    // Static Public Properties
    // =========================================================================

    /**
     * @var bool
     */
    public static $pendingImageTransforms = false;

    // Static Private Properties
    // =========================================================================

    private static $transforms = [
        'base' => [
            'format' => null,
            'quality' => self::SOCIAL_TRANSFORM_QUALITY,
            'width' => 1200,
            'height' => 630,
            'mode' => 'crop',
        ],
        'facebook' => [
            'width' => 1200,
            'height' => 630,
        ],
        'twitter-summary' => [
            'width' => 800,
            'height' => 800,
        ],
        'twitter-large' => [
            'width' => 800,
            'height' => 418,
        ],
        'schema-logo' => [
            'format' => 'png',
            'width' => 600,
            'height' => 600,
            'mode' => 'fit',
        ],
    ];

    private static $cachedAssetsElements = [];

    // Static Methods
    // =========================================================================

    /**
     * Transform the $asset for social media sites in $transformName and
     * optional $siteId
     *
     * @param int|Asset $asset the Asset or Asset ID
     * @param string $transformName the name of the transform to apply
     * @param int|null $siteId
     * @param string $transformMode
     *
     * @return string URL to the transformed image
     */
    public static function socialTransform(
        $asset,
        $transformName = '',
        $siteId = null,
        $transformMode = null,
    ): string {
        $url = '';
        $transform = self::createSocialTransform($transformName);
        // Let them override the mode
        if (empty($transformMode)) {
            $transformMode = $transform->mode ?? 'crop';
        }
        if ($transform !== null) {
            $transform->mode = $transformMode;
        }
        $asset = self::assetFromAssetOrIdOrQuery($asset, $siteId);
        if (($asset !== null) && ($asset instanceof Asset)) {
            // Make sure the format is an allowed format, otherwise explicitly change it
            $mimeType = $asset->getMimeType();
            if ($transform !== null && !in_array($mimeType, self::ALLOWED_SOCIAL_MIME_TYPES, false)) {
                $transform->format = self::DEFAULT_SOCIAL_FORMAT;
            }
            // Craft 6 resolves transforms through the asset's configured driver.
            try {
                $url = $transform === null ? $asset->getUrl() : $asset->transform($transform)->url;
            } catch (AssetTransformException|FilesystemException|InvalidConfigException|NotSupportedException $e) {
                $url = null;
            }
            if ($url === null) {
                $url = '';
            }
            // If we have a url, add an `mtime` param to cache bust
            if (!empty($url) && empty(parse_url($url, PHP_URL_QUERY))) {
                $now = new DateTime();
                $newestChange = max($asset->dateModified, $asset->dateUpdated);
                $url = UrlHelper::url($url, [
                    'mtime' => $newestChange->getTimestamp(),
                ]);
            }
        }
        // Check to see if the $url contains a pending image transform
        if (!empty($url) && StringHelper::contains($url, 'assets/generate-transform')) {
            self::$pendingImageTransforms = true;
        }

        return $url;
    }

    /**
     * @param int|Asset $asset the Asset or Asset ID
     * @param string $transformName the name of the transform to apply
     * @param int|null $siteId
     * @param string $transformMode
     *
     * @return string width of the transformed image
     */
    public static function socialTransformWidth(
        $asset,
        $transformName = '',
        $siteId = null,
        $transformMode = null,
    ): string {
        $width = '';
        $transform = self::createSocialTransform($transformName);
        // Let them override the mode
        if ($transform !== null) {
            $transform->mode = $transformMode ?? $transform->mode;
        }
        $asset = self::assetFromAssetOrIdOrQuery($asset, $siteId);
        if ($asset instanceof Asset) {
            $width = $asset->getWidth($transform);
            if ($width === null) {
                $width = '';
            }
            $width = (string)$width;
        }

        return $width;
    }

    /**
     * @param int|Asset $asset the Asset or Asset ID
     * @param string $transformName the name of the transform to apply
     * @param int|null $siteId
     * @param string $transformMode
     *
     * @return string width of the transformed image
     */
    public static function socialTransformHeight(
        $asset,
        $transformName = '',
        $siteId = null,
        $transformMode = null,
    ): string {
        $height = '';
        $transform = self::createSocialTransform($transformName);
        // Let them override the mode
        if ($transform !== null) {
            $transform->mode = $transformMode ?? $transform->mode;
        }
        $asset = self::assetFromAssetOrIdOrQuery($asset, $siteId);
        if ($asset instanceof Asset) {
            $height = $asset->getHeight($transform);
            if ($height === null) {
                $height = '';
            }
            $height = (string)$height;
        }

        return $height;
    }

    /**
     * Return an array of Asset elements from an array of element IDs
     *
     * @param array|string $assetIds
     * @param int|null $siteId
     *
     * @return array
     */
    public static function assetElementsFromIds($assetIds, $siteId = null): array
    {
        $elements = Craft::$app->getElements();
        $assets = [];
        if (!empty($assetIds)) {
            if (is_array($assetIds)) {
                foreach ($assetIds as $assetId) {
                    if (!empty($assetId)) {
                        $assets[] = $elements->getElementById((int)$assetId, Asset::class, $siteId);
                    }
                }
            } else {
                $assetId = $assetIds;
                $assets[] = $elements->getElementById((int)$assetId, Asset::class, $siteId);
            }
        }

        return array_filter($assets);
    }

    // Protected Static Methods
    // =========================================================================

    /**
     * Return an asset from either an id or an asset
     *
     * @param int|array|ElementCollection|Asset|ElementQuery $asset the Asset or Asset ID or ElementQuery
     * @param int|null $siteId
     *
     * @return Asset|array|ElementInterface|null
     */
    protected static function assetFromAssetOrIdOrQuery($asset, $siteId = null)
    {
        if (empty($asset)) {
            return null;
        }
        // If it's an array (eager loaded Element query), return the first element
        if (is_array($asset)) {
            return reset($asset);
        }
        // If it's an asset already, just return it
        if ($asset instanceof Asset) {
            return $asset;
        }
        // If it is a Collection, resolve that to an asset
        if ($asset instanceof ElementCollection) {
            return $asset->first();
        }
        // If it is an ElementQuery, resolve that to an asset
        if ($asset instanceof ElementQuery) {
            return $asset->one();
        }

        $resolvedAssetId = (int)$asset;
        $resolvedSiteId = $siteId ?? 0;
        if (isset(self::$cachedAssetsElements[$resolvedAssetId][$resolvedSiteId])) {
            return self::$cachedAssetsElements[$resolvedAssetId][$resolvedSiteId];
        }

        $asset = Craft::$app->getAssets()->getAssetById($resolvedAssetId, $siteId);
        self::$cachedAssetsElements[$resolvedAssetId][$resolvedSiteId] = $asset;

        return $asset;
    }

    /**
     * Create a transform from the passed in $transformName
     *
     * @param string $transformName the name of the transform to apply
     *
     * @return ImageTransformModel|null
     */
    protected static function createSocialTransform($transformName = 'base')
    {
        $transform = null;
        if (!empty($transformName)) {
            $config = array_merge(
                self::$transforms['base'],
                self::$transforms[$transformName] ?? self::$transforms['base']
            );
            $transform = new ImageTransformModel($config);
        }

        return $transform;
    }
}
