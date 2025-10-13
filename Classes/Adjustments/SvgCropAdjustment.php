<?php

namespace Sitegeist\Kaleidoscope\Svg\Adjustments;

use Neos\Flow\Annotations as Flow;
use Contao\ImagineSvg\Image as SvgImage;
use Contao\ImagineSvg\SvgBox;
use Imagine\Image\Point;
use Neos\Media\Domain\Model\Adjustment\CropImageAdjustment;

/**
 * An adjustment for resizing an image
 *
 * @Flow\Entity
 */
class SvgCropAdjustment extends CropImageAdjustment implements SvgImageAdjustmentInterface
{
    public static function createFromCropImageAdjustment(CropImageAdjustment $cropImageAdjustment): self
    {
        $res = new self();
        $res->setWidth($cropImageAdjustment->getWidth());
        $res->setHeight($cropImageAdjustment->getHeight());
        $res->setX($cropImageAdjustment->getX());
        $res->setY($cropImageAdjustment->getY());
        $res->setAspectRatio($cropImageAdjustment->getAspectRatio());
        return $res;
    }

    public function applyToSvgImage(SvgImage $image): SvgImage
    {
        $desiredAspectRatio = $this->getAspectRatio();
        if ($desiredAspectRatio !== null) {
            $originalWidth = $image->getSize()->getWidth();
            $originalHeight = $image->getSize()->getHeight();

            [$newX, $newY, $newWidth, $newHeight] = self::calculateDimensionsByAspectRatio($originalWidth, $originalHeight, $desiredAspectRatio);

            $point = new Point($newX, $newY);
            $box = new SvgBox($newWidth, $newHeight);
        } else {
            $point = new Point($this->x, $this->y);
            $box = new SvgBox($this->width, $this->height);
        }
        return $image->crop($point, $box);
    }
}
