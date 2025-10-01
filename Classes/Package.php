<?php

declare(strict_types=1);

namespace Sitegeist\Kaleidoscope\SvgDimensions;

use Dsr\NeosAdjustments\ContentRepository\DimensionSynchronisation\DimensionSyncronizationHelper;
use Dsr\NeosAdjustments\ContentRepository\ResetOnCopyHandler;
use Dsr\NeosAdjustments\ContentRepository\UrlizeOnAdoptionHandler;
use Neos\ContentRepository\Domain\Model\Node;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Package\Package as BasePackage;
use Neos\ContentRepository\Domain\Service\Context;
use Neos\Media\Domain\Service\AssetService;
use Sitegeist\Kaleidoscope\SvgDimensions\MediaRepository\SvgDimensionConnector;
use Sitegeist\LostInTranslation\ContentRepository\NodeTranslationService;

/**
 * The Neos Package
 */
class Package extends BasePackage
{
    /**
     * @param Bootstrap $bootstrap The current bootstrap
     * @return void
     */
    public function boot(Bootstrap $bootstrap)
    {
        $dispatcher = $bootstrap->getSignalSlotDispatcher();

        $dispatcher->connect(
            AssetService::class,
            'assetCreated',
            SvgDimensionConnector::class,
            'assetCreated'
        );

        $dispatcher->connect(
            AssetService::class,
            'assetResourceReplaced',
            SvgDimensionConnector::class,
            'assetResourceReplaced'
        );

    }
}
