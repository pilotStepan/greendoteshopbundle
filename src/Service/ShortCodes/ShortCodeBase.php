<?php
namespace Greendot\EshopBundle\Service\ShortCodes;

abstract class ShortCodeBase
{
    /**
     * @return string
     * Gets regex that will be searched in content
     */
    abstract function regex(): string;

    /**
     * has supported fields by class which define where we can look for shortcodes and replace them
     *
     * example:
     * [
     * Greendot\EshopBundle\Entity\Project\Product => ['html', 'description']
     * Greendot\EshopBundle\Entity\Project\Category => ['html']
     * ]
     * @return array
     */
    abstract function supportedFields(): array;

    /**
     * replaces regex with something and can work with shortcode data
     */
    abstract function replaceableContent(object $object, ?array $data = null): string;

    final public function getFields(string $objectName): array
    {
        if (!$this->supports($objectName)) throw new \Exception('Shortcode does not support this object');

        return $this->supportedFields()[$objectName];
    }

    /**
     * $object ex. Greendot\EshopBundle\Entity\Project\Product
     * $field ex. html, description
     *
     * if given shortcode supports this object returns true
     *
     * @param string $objectName
     * @param ?string $field
     * @return bool
     */
    final public function supports(string $objectName, ?string $field = null) :bool
    {
        //for all fields
        if (is_null($field)) return in_array($objectName, array_keys($this->supportedFields()));

        //for specific field
        if (in_array($objectName, array_keys($this->supportedFields()))){
            return in_array($field, $this->supportedFields()[$objectName]);
        }
        return false;
    }

    final function replaceField(object $object, string $field): object
    {
        $getter = 'get' . ucfirst($field);
        $setter = 'set' . ucfirst($field);
        $content = $object->$getter();

        preg_match_all($this->regex(), $content, $matches);
        if (!isset($matches)) return $object;

        foreach ($matches[0] as $key => $shortCode) {
            $data = null;
            if (isset($matches[1][$key])){
                $data = $matches[1][$key];
                $data = json_decode($data);
                if (!is_null($data)) $data = (array) $data;
            }
            $replacedContent = $this->replaceableContent($object, $data);
            $content = str_replace($shortCode, $replacedContent, $content);
        }
        $object->$setter($content);
        return $object;
    }

    final function replaceAll(object $object): object
    {
        foreach ($this->getFields(get_class($object)) as $field) {
            $this->replaceField($object, $field);
        }
        return $object;
    }


}