<?php

namespace Sitegeist\Kaleidoscope\Svg\Aspects;

use Contao\ImagineSvg\Imagine as SvgImagine;
use Imagine\Image\Box;
use Imagine\Image\Point;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Flow\Persistence\Doctrine\PersistenceManager;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Flow\Utility\Algorithms;
use Neos\Flow\Utility\Environment;
use Neos\Media\Domain\Model\Adjustment\CropImageAdjustment;
use Neos\Media\Domain\Model\Adjustment\ImageAdjustmentInterface;
use Neos\Media\Domain\Model\Adjustment\ResizeImageAdjustment;
use Psr\Log\LoggerInterface;
use Sitegeist\Kaleidoscope\Svg\Adjustments\SvgCropAdjustment;
use Sitegeist\Kaleidoscope\Svg\Adjustments\SvgResizeAdjustment;

/**
 * @Flow\Scope("singleton")
 * @Flow\Aspect
 */
class ImageServiceAspect
{
    /**
     * @Flow\Inject
     * @var Environment
     */
    protected $environment;

    /**
     * @var ResourceManager
     * @Flow\Inject
     */
    protected $resourceManager;

    /**
     * @var PersistenceManager
     * @Flow\Inject
     */
    protected $persistenceManager;

    /**
     * @see \Neos\Media\Domain\Service\ImageService::getImageSize
     *
     * @Flow\Around("method(Neos\Media\Domain\Service\ImageService->getImageSize())")
     * @param \Neos\Flow\Aop\JoinPointInterface $joinPoint The current join point
     * @return array{width: ?int, height: ?int}
     */
    public function getImageSize(JoinPointInterface $joinPoint): array
    {
        /** @var PersistentResource $resource */
        $resource = $joinPoint->getMethodArgument('resource');
        if ($resource->getMediaType() !== 'image/svg+xml') {
            return $joinPoint->getAdviceChain()->proceed($joinPoint);
        }

        $resourceStream = $resource->getStream();
        if (is_bool($resourceStream)) {
            return ['width' => null, 'height' => null];
        }
        try {
            $svgImage = (new SvgImagine())->read($resourceStream);
        } catch (\Exception $e) {
            return ['width' => null, 'height' => null];
        }

        $size = $svgImage->getSize();
        return ['width' => $size->getWidth(), 'height' => $size->getHeight()];
    }

    /**
     * @see \Neos\Media\Domain\Service\ImageService::processImage
     *
     * @Flow\Around("method(Neos\Media\Domain\Service\ImageService->processImage())")
     * @param \Neos\Flow\Aop\JoinPointInterface $joinPoint The current join point
     * @return array{resource: PersistentResource, width: ?int, height: ?int}
     */
    public function processImage(JoinPointInterface $joinPoint): array
    {
        /** @var PersistentResource $originalResource */
        $originalResource = $joinPoint->getMethodArgument('originalResource');
        if ($originalResource->getMediaType() !== 'image/svg+xml') {
            return $joinPoint->getAdviceChain()->proceed($joinPoint);
        }

        // we are sure to handle svg images here and can proceed
        /** @var ImageAdjustmentInterface[] $adjustments */
        $adjustments = $joinPoint->getMethodArgument('adjustments');

        $originalResourceStream = $originalResource->getStream();

        if (is_bool($originalResourceStream)) {
            return $this->fallbackToOriginalResource($originalResource);
        }

        try {
            $svgImage = (new SvgImagine())->read($originalResourceStream);
        } catch (\Exception) {
            return $this->fallbackToOriginalResource($originalResource);
        }

        $size = $svgImage->getSize();

        foreach ($adjustments as $adjustment) {
            if ($adjustment instanceof CropImageAdjustment) {
                $svgAdjustment = SvgCropAdjustment::createFromCropImageAdjustment($adjustment);
                $svgImage = $svgAdjustment->applyToSvgImage($svgImage);
                $size = $svgImage->getSize();
            }
            if ($adjustment instanceof ResizeImageAdjustment) {
                $svgAdjustment = SvgResizeAdjustment::createFromResizeImageAdjustment($adjustment);
                $svgImage = $svgAdjustment->applyToSvgImage($svgImage);
                $size = $svgImage->getSize();
            }
        }

        $transformedImageTemporaryPathAndFilename = $this->environment->getPathToTemporaryDirectory() . 'ProcessedImage-' . Algorithms::generateRandomString(13) . '.svg';

        $svgImage->save($transformedImageTemporaryPathAndFilename);
        $resource = $this->resourceManager->importResource($transformedImageTemporaryPathAndFilename, $originalResource->getCollectionName());
        $resource->setFilename($originalResource->getFilename());
        unlink($transformedImageTemporaryPathAndFilename);

        return [
            'width' => $size->getWidth(),
            'height' => $size->getHeight(),
            'resource' => $resource,
        ];
    }

    /**
     * @param PersistentResource $originalResource
     * @return array{resource: PersistentResource, width: ?int, height: ?int}
     */
    protected function fallbackToOriginalResource(PersistentResource $originalResource): array
    {
        return [
            'width' => null,
            'height' => null,
            'resource' => $originalResource
        ];
    }
}
