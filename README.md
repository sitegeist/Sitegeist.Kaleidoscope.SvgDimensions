# Sitegeist.Kaleidoscope.SvgDimensions
## SVG cropping and resizing for neos via contao/imagine-svg 

This package allows to crop and resize SVG images in Neos and also will 
detect dimensions in newly uploaded SVG images to properly handle the different 
image orientations of SVG images.

### Usage

Mainly the package only has to be installed to allow cropping of svg images. 

However there is a command to detect the dimensions of already uploaded for 
SVG images via cli.

```
./flow svgimage:calculatedimensions [<options>]

OPTIONS:
  --force              re calculate dimensions for all svg assets
```

### Authors & Sponsors

* Martin Ficzel - ficzel@sitegeist.de

*The development and the public-releases of this package is generously sponsored
by our employer http://www.sitegeist.de.*

## Installation

Sitegeist.Kaleidoscope.SvgDimensions is available via packagist run `composer require sitegeist/kaleidoscope-svgdimensions`.
We use semantic versioning so every breaking change will increase the major-version number.

## Contribution

We will gladly accept contributions. Please send us pull requests.
