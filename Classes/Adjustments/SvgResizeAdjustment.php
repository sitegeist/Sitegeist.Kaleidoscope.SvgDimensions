<?php

namespace Sitegeist\Kaleidoscope\Svg\Adjustments;

use Neos\Flow\Annotations as Flow;
use Contao\ImagineSvg\Image as SvgImage;
use Imagine\Image\Point;
use Neos\Media\Domain\Model\Adjustment\ResizeImageAdjustment;
use Neos\Media\Domain\Model\ImageInterface;

/**
 * An adjustment for resizing an image
 *
 * @Flow\Entity
 */
class SvgResizeAdjustment extends ResizeImageAdjustment implements SvgImageAdjustmentInterface
{
    public static function createFromResizeImageAdjustment(ResizeImageAdjustment $resizeImageAdjustment): self
    {
        $res = new self();
        $res->setWidth($resizeImageAdjustment->getWidth());
        $res->setHeight($resizeImageAdjustment->getHeight());
        $res->setMaximumWidth($resizeImageAdjustment->getMaximumWidth());
        $res->setMaximumHeight($resizeImageAdjustment->getMaximumHeight());
        $res->setMinimumWidth($resizeImageAdjustment->getMinimumWidth());
        $res->setMinimumHeight($resizeImageAdjustment->getMinimumHeight());
        $res->setRatioMode($resizeImageAdjustment->getRatioMode());
        $res->setAllowUpScaling(true);
        return $res;
    }

    public function applyToSvgImage(SvgImage $image): SvgImage
    {
        return $this->resizeSvgImage($image, $this->ratioMode);
    }

    protected function resizeSvgImage(SvgImage $image, string $mode = ImageInterface::RATIOMODE_INSET): SvgImage
    {
        if (
            $mode !== ImageInterface::RATIOMODE_INSET &&
            $mode !== ImageInterface::RATIOMODE_OUTBOUND
        ) {
            throw new \InvalidArgumentException('Invalid mode specified', 1574686891);
        }

        $imageSize = $image->getSize();
        $requestedDimensions = $this->calculateDimensions($imageSize);

        $image->strip();

        $resizeDimensions = $requestedDimensions;
        if ($mode === ImageInterface::RATIOMODE_OUTBOUND) {
            $resizeDimensions = $this->calculateOutboundScalingDimensions($imageSize, $requestedDimensions);
        }

        $image->resize($resizeDimensions);

        if ($mode === ImageInterface::RATIOMODE_OUTBOUND) {
            $image->crop(new Point(
                (int)max(0, round(($resizeDimensions->getWidth() - $requestedDimensions->getWidth()) / 2)),
                (int)max(0, round(($resizeDimensions->getHeight() - $requestedDimensions->getHeight()) / 2))
            ), $requestedDimensions);
        }

        return $image;
    }
}
