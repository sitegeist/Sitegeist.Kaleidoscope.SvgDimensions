<?php

namespace Sitegeist\Kaleidoscope\SvgDimensions\Extractor;

use Neos\Media\Domain\Model\Image;
use Neos\Media\Domain\Model\ImageVariant;
use Sitegeist\Kaleidoscope\SvgDimensions\Domain\SvgDimensions;
use Contao\ImagineSvg\Imagine as SvgImagine;
use Contao\ImagineSvg\SvgBox;

class SvgDimensionExtractor {

    public static function extractSvgImageSizes(Image|ImageVariant $image): ?SvgBox
    {
        if ($image->getMediaType() !== 'image/svg+xml') {
            return null;
        }

        try {
            $originalResourceStream = $image->getResource()->getStream();
        } catch (\Exception) {
            return null;
        }

        if ($originalResourceStream === false) {
            return null;
        }

        try {
            $image = (new SvgImagine())->read($originalResourceStream);
        } catch (\Exception) {
            if ($originalResourceStream !== null) {
                fclose($originalResourceStream);
            }
            return null;
        }

        fclose($originalResourceStream);
        return $image->getSize();
    }
}
