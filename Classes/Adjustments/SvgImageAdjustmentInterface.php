<?php

namespace Sitegeist\Kaleidoscope\Svg\Adjustments;

use Contao\ImagineSvg\Image as SvgImage;

interface SvgImageAdjustmentInterface
{
    public function applyToSvgImage(SvgImage $image): SvgImage;
}
