<?php

declare(strict_types=1);

namespace Sitegeist\Kaleidoscope\Svg\Command;

use Contao\ImagineSvg\Imagine as SvgImagine;
use Contao\ImagineSvg\SvgBox;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Persistence\Doctrine\PersistenceManager;
use Neos\Media\Domain\Model\Image;
use Neos\Media\Domain\Repository\ImageRepository;
use Sitegeist\Kaleidoscope\Svg\Extractor\SvgDimensionExtractor;

class SvgCommandController extends CommandController
{
    #[Flow\Inject]
    public ImageRepository $imageRepository;

    #[Flow\Inject]
    public PersistenceManager $persistenceManager;

    /**
     * Calculate missing dimensions for SVG assets
     *
     * @param bool $force update all svg asset dimensions
     */
    public function refreshDimensionsCommand(bool $force = false): void
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
                if ($force === true || ($image->getWidth() == 0 || $image->getHeight() == 0)) {
                    $count++;
                    $image->refresh();
                    // the image repository tries magic we need to circumvent
                    $this->persistenceManager->update($image);
                }
            }
        }

        $this->output->progressFinish();
        $this->outputLine();
        $this->outputLine('Added dimensions to %s SVG Assets', [$count]);
    }
}
