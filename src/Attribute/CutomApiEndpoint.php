<?php

namespace Greendot\EshopBundle\Attribute;

use Attribute;

/**
 * Marks endpoint as CustomApiEndpoint for ApiRequestDetector service
 */
#[Attribute(Attribute::TARGET_METHOD)]
class CustomApiEndpoint
{}