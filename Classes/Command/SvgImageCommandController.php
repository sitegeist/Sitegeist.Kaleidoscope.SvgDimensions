<?php

declare(strict_types=1);

namespace Sitegeist\Kaleidoscope\SvgDimensions\Command;

use Contao\ImagineSvg\Imagine as SvgImagine;
use Contao\ImagineSvg\SvgBox;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Persistence\Doctrine\PersistenceManager;
use Neos\Media\Domain\Model\Image;
use Neos\Media\Domain\Repository\ImageRepository;
use Sitegeist\Kaleidoscope\SvgDimensions\Extractor\SvgDimensionExtractor;

class SvgImageCommandController extends CommandController
{
    #[Flow\Inject]
    public ImageRepository $imageRepository;

    #[Flow\Inject]
    public PersistenceManager $persistenceManager;

    /**
     * Calculate dimensions for SVG assets that do not have them yet
     *
     * @param bool $force re calculate dimensions for all svg assets
     */
    public function calculateDimensionsCommand(bool $force = false): void
    {
        $this->outputLine('Looking for SVG Assets without dimensions');

        $queryResult = $this->imageRepository->findAll();
        $this->output->progressStart($queryResult->count());
        $count = 0;

        /**
         * @var Image $image
         */
        foreach ($queryResult as $image) {
            $this->output->progressAdvance(1);
            if ($image->getResource()->getMediaType() === 'image/svg+xml') {
                if ($force === true || $image->getWidth() == 0 || $image->getHeight() == 0) {
                    $resourceStream = $image->getResource()->getStream();
                    if (is_bool($resourceStream)) {
                        continue;
                    }
                    try {
                        $svgImage = (new SvgImagine())->read($resourceStream);
                        $svgSize = $svgImage->getSize();
                    } catch (\Exception $e) {
                        continue;
                    }

                    if ($svgSize instanceof SvgBox && $svgSize->getWidth() > 0 && $svgSize->getHeight() > 0) {
                        $objectReflection = new \ReflectionObject($image);

                        $widthProperty = $objectReflection->getProperty('width');
                        $widthProperty->setAccessible(true);
                        $widthProperty->setValue($image, $svgSize->getWidth());

                        $heightProperty = $objectReflection->getProperty('height');
                        $heightProperty->setAccessible(true);
                        $heightProperty->setValue($image, $svgSize->getHeight());

                        $this->persistenceManager->update($image);
                        $count++;
                    }
                }
            }
        }

        $this->output->progressFinish();
        $this->outputLine();
        $this->outputLine('Added dimensions to %s SVG Assets', [$count]);
    }
}
