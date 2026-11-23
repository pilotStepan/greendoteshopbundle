<?php

namespace Greendot\EshopBundle\Tests\Entity;

use Gedmo\Mapping\Annotation\Translatable;
use Greendot\EshopBundle\Entity\Project\Upload;
use PHPUnit\Framework\TestCase;

/**
 * File fields are translatable so each language can carry its own replacement file (localised images).
 * Text fields stay translatable as before.
 */
class UploadTranslatableFieldsTest extends TestCase
{
    public function testFileFieldsAreGedmoTranslatable(): void
    {
        foreach (Upload::FILE_FIELDS as $field) {
            $attributes = (new \ReflectionProperty(Upload::class, $field))->getAttributes(Translatable::class);

            $this->assertNotEmpty($attributes, sprintf('Upload::%s must be #[Gedmo\Translatable]', $field));
        }
    }

    public function testTextFieldsStayTranslatable(): void
    {
        foreach (['name', 'title', 'shortDescription', 'description'] as $field) {
            $attributes = (new \ReflectionProperty(Upload::class, $field))->getAttributes(Translatable::class);

            $this->assertNotEmpty($attributes, sprintf('Upload::%s must stay #[Gedmo\Translatable]', $field));
        }
    }
}
