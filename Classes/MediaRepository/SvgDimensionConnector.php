<?php

namespace Sitegeist\Kaleidoscope\SvgDimensions\MediaRepository;

use Contao\ImagineSvg\SvgBox;
use Imagine\Image\ImageInterface;
use Neos\Flow\Persistence\Doctrine\PersistenceManager;
use Neos\Media\Domain\Model\Asset;
use Neos\Media\Domain\Model\Image;
use Neos\Media\Domain\Model\ImageVariant;
use Neos\Media\Domain\Repository\AssetRepository;
use Psr\Log\LoggerInterface;
use Sitegeist\Kaleidoscope\SvgDimensions\Domain\SvgDimensions;
use Sitegeist\Kaleidoscope\SvgDimensions\Extractor\SvgDimensionExtractor;

class SvgDimensionConnector {

    public function __construct(
        private AssetRepository $assetRepository,
        private PersistenceManager $persistenceManager
    ) {

    }
    public function assetCreated(Asset $asset) {
        if (
            ($asset instanceof Image || $asset instanceof ImageVariant)
            && $asset->getResource()->getMediaType() === 'image/svg+xml'
        ) {
            $this->updateDimensions($asset);
        }
    }

    public function assetResourceReplaced(Asset $asset) {
        if (
            ($asset instanceof Image || $asset instanceof ImageVariant)
            && $asset->getResource()->getMediaType() === 'image/svg+xml'
        ) {
            $this->updateDimensions($asset);
        }
    }

    private function updateDimensions(Image|ImageVariant $imageOrVariant) {
        $dimensions = SvgDimensionExtractor::extractSvgImageSizes($imageOrVariant);

        if ($dimensions === null) {
            $dimensions = SvgBox::createTypeNone();
        }

        $objectReflection = new \ReflectionObject($imageOrVariant);

        $widthProperty = $objectReflection->getProperty('width');
        $widthProperty->setAccessible(true);
        $widthProperty->setValue($imageOrVariant, $dimensions->getWidth());

        $heightProperty = $objectReflection->getProperty('height');
        $heightProperty->setAccessible(true);
        $heightProperty->setValue($imageOrVariant, $dimensions->getWidth());

        $this->persistenceManager->update($imageOrVariant);
    }
}
