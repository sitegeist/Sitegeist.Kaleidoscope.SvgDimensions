<?php

namespace Sitegeist\Kaleidoscope\SvgDimensions\Aspects;

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
use Psr\Log\LoggerInterface;

/**
 * @Flow\Scope("singleton")
 * @Flow\Aspect
 */
class ImageServiceAspect {

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
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var PersistenceManager
     * @Flow\Inject
     */
    protected $persistenceManager;

    /**
     * @Flow\Around("method(Neos\Media\Domain\Service\ImageService->processImage())")
     * @param \Neos\Flow\Aop\JoinPointInterface $joinPoint The current join point
     * @return array{resource: PersistentResource, width: ?int, height: ?int}
     * @deprecated will be removed with Neos 9
     */
    public function processImage(JoinPointInterface $joinPoint): array
    {
        /** @var PersistentResource $originalResource */
        $originalResource = $joinPoint->getMethodArgument('originalResource');
        if ($originalResource->getMediaType() !== 'image/svg+xml') {
            return $joinPoint->getAdviceChain()->proceed($joinPoint);
        }

        // we are sure to handle svg images here and can proceed
        /** @var array $adjustments */
        $adjustments = $joinPoint->getMethodArgument('adjustments');

        $this->logger->info("processImage Aspect", [$originalResource, $adjustments]);

        $originalResourceStream = $originalResource->getStream();

        try {
            $svgImage = (new SvgImagine())->read($originalResourceStream);
        } catch (\Imagine\Exception\InvalidArgumentException $e) {
            return $this->fallbackToOriginalResource($originalResource);
        }

        $size = $svgImage->getSize();


        foreach ($adjustments as $adjustment) {
            if ($adjustment instanceof CropImageAdjustment) {
                $point = new Point($adjustment->getX(), $adjustment->getY());
                $box = new Box($adjustment->getWidth(), $adjustment->getHeight());
                $svgImage = $svgImage->crop($point, $box);
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
    protected function fallbackToOriginalResource(PersistentResource $originalResource): array {
        $originalResourceStream = $originalResource->getStream();
        $resource = $this->resourceManager->importResource($originalResourceStream, $originalResource->getCollectionName());
        fclose($originalResourceStream);
        $resource->setFilename($originalResource->getFilename());
        return [
            'width' => null,
            'height' => null,
            'resource' => $resource
        ];
    }
}
