<?php
declare(strict_types=1);

namespace Sitegeist\Kaleidoscope\SvgDimensions\Domain;

use Imagine\Image\BoxInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Utility\Algorithms;
use Neos\Media\Domain\Model\ImageInterface;
use Neos\Media\Domain\Model\Thumbnail;
use Neos\Media\Domain\Model\ThumbnailGenerator\AbstractThumbnailGenerator;
use Contao\ImagineSvg\Imagine as SvgImagine;
use Contao\ImagineSvg\SvgBox;

class SvgThumbnailGenerator extends AbstractThumbnailGenerator {

    /**
     * The priority for this thumbnail generator.
     *
     * @var integer
     * @api
     */
    protected static $priority = 10;

    public function canRefresh(Thumbnail $thumbnail)
    {
        return (
            $thumbnail->getOriginalAsset() instanceof ImageInterface
            && $thumbnail->getOriginalAsset()->getResource()->getMediaType() === 'image/svg+xml'
            && $thumbnail->getOriginalAsset()->getWidth() && $thumbnail->getOriginalAsset()->getHeight()
        );
    }

    public function refresh(Thumbnail $thumbnail)
    {
        try {

            $originalResource = $thumbnail->getOriginalAsset()->getResource();
            $originalResourceStream = $originalResource->getStream();

            try {
                $image = (new SvgImagine())->read($originalResourceStream);
                $originalDimensions = $image->getSize();
            } catch (\Imagine\Exception\InvalidArgumentException $e) {
                return null;
            }

            $targetDimensions = $this->calculateDimensions(
                $originalDimensions,
                $thumbnail->getConfigurationValue('width'),
                $thumbnail->getConfigurationValue('maximumWidth'),
                $thumbnail->getConfigurationValue('height'),
                $thumbnail->getConfigurationValue('maximumHeight'),
                $thumbnail->getConfigurationValue('ratioMode'),
            );

            if (
                $targetDimensions->getType() !== $originalDimensions->getWidth()
                || $targetDimensions->getWidth() !== $originalDimensions->getWidth()
                || $targetDimensions->getHeight() !== $originalDimensions->getHeight()
            ) {
                $transformedImageTemporaryPathAndFilename = $this->environment->getPathToTemporaryDirectory() . 'ProcessedImage-' . Algorithms::generateRandomString(13) . '.svg';
                $adjustedImage = $image
                    ->resize($targetDimensions)
                    ->save($transformedImageTemporaryPathAndFilename);

                $adjustedImageResource = $this->resourceManager->importResource($transformedImageTemporaryPathAndFilename, $originalResource->getCollectionName());
                $adjustedImageResource->setFilename($originalResource->getFilename());
                unlink($transformedImageTemporaryPathAndFilename);
            } else {
                $adjustedImage = $image;
                $adjustedImageResource = $this->resourceManager->importResource($originalResourceStream, $originalResource->getCollectionName());
                $adjustedImageResource->setFilename($originalResource->getFilename());
            }

            fclose($originalResourceStream);

            $thumbnail->setResource($adjustedImageResource);

            $adjustedImageSize = $adjustedImage->getSize();
            $thumbnail->setWidth($adjustedImageSize->getWidth());
            $thumbnail->setHeight($adjustedImageSize->getHeight());

        } catch (\Exception $exception) {
            $message = sprintf('Unable to generate thumbnail for the given image (filename: %s, SHA1: %s)', $thumbnail->getOriginalAsset()->getResource()->getFilename(), $thumbnail->getOriginalAsset()->getResource()->getSha1());
            throw new Exception\NoThumbnailAvailableException($message, 1433109654, $exception);
        }
    }

    /**
     * Calculates and returns the dimensions the image should have according all parameters set
     * in this adjustment.
     *
     * @param SvgBox $originalDimensions Dimensions of the unadjusted image
     * @return SvgBox
     * @see \Neos\Media\Domain\Model\Adjustment\ResizeImageAdjustment::calculateDimensions
     */
    protected function calculateDimensions(SvgBox $originalDimensions, ?int $width = null, ?int $height = null, ?int $maximumWidth = null, ?int $maximumHeight = null, ?string $ratioMode = null): SvgBox
    {
        $newDimensions = clone $originalDimensions;

        switch (true) {
            // height and width are set explicitly:
            case ($width !== null && $height !== null):
                $newDimensions = $this->calculateWithFixedDimensions($originalDimensions, $width, $height, $ratioMode);
                break;
            // only width is set explicitly:
            case ($width !== null):
                $newDimensions = $this->calculateScalingToWidth($originalDimensions, $width);
                break;
            // only height is set explicitly:
            case ($height !== null):
                $newDimensions = $this->calculateScalingToHeight($originalDimensions, $height);
                break;
        }

        // We apply maximum dimensions and scale the new dimensions proportionally down to fit into the maximum.
        if ($maximumWidth !== null && $newDimensions->getWidth() > $maximumWidth) {
            $newDimensions = $newDimensions->widen($maximumWidth);
        }

        if ($maximumHeight !== null && $newDimensions->getHeight() > $maximumHeight) {
            $newDimensions = $newDimensions->heighten($maximumHeight);
        }

        if ($newDimensions->getType() === SvgBox::TYPE_ABSOLUTE) {
            return $newDimensions;
        }
        return SvgBox::createTypeAbsolute($newDimensions->getWidth(), $newDimensions->getHeight());
    }


    /**
     * @param BoxInterface $originalDimensions
     * @param integer $requestedWidth
     * @param integer $requestedHeight
     * @return BoxInterface
     */
    protected function calculateWithFixedDimensions(SvgBox $originalDimensions, int $requestedWidth, int $requestedHeight, ?string $ratioMode = null): SvgBox
    {
        if ($ratioMode === ImageInterface::RATIOMODE_OUTBOUND) {
            return $this->calculateOutboundBox($originalDimensions, $requestedWidth, $requestedHeight);
        }

        $newDimensions = clone $originalDimensions;

        $ratios = [
            $requestedWidth / $originalDimensions->getWidth(),
            $requestedHeight / $originalDimensions->getHeight()
        ];

        $ratio = min($ratios);
        return $newDimensions->scale($ratio);
    }

    /**
     * Calculate the final dimensions for an outbound box. usually exactly the requested width and height unless that
     * would require upscaling and it is not allowed.
     *
     * @param SvgBox $originalDimensions
     * @param integer $requestedWidth
     * @param integer $requestedHeight
     * @return SvgBox
     */
    protected function calculateOutboundBox(SvgBox $originalDimensions, int $requestedWidth, int $requestedHeight): SvgBox
    {
        return new SvgBox($requestedWidth, $requestedHeight, $originalDimensions->getType());
    }

    /**
     * Calculates new dimensions with a requested width applied. Takes upscaling into consideration.
     *
     * @param SvgBox $originalDimensions
     * @param integer $requestedWidth
     * @return SvgBox
     */
    protected function calculateScalingToWidth(SvgBox $originalDimensions, int $requestedWidth): SvgBox
    {
        $newDimensions = clone $originalDimensions;
        $newDimensions = $newDimensions->widen($requestedWidth);

        return $newDimensions;
    }

    /**
     * Calculates new dimensions with a requested height applied. Takes upscaling into consideration.
     *
     * @param SvgBox $originalDimensions
     * @param integer $requestedHeight
     * @return SvgBox
     */
    protected function calculateScalingToHeight(SvgBox $originalDimensions, int $requestedHeight): SvgBox
    {
        $newDimensions = clone $originalDimensions;
        $newDimensions = $newDimensions->heighten($requestedHeight);

        return $newDimensions;
    }
}
