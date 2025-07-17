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
    abstract function replaceableContent(string $className, ?array $data = null): string;

    final public function getFields(string $objectName): array
    {
        if (!$this->supports($objectName)) throw new \Exception('Shortcode does not support this object');

        return $this->supportedFields()[$objectName];
    }

    /**
     * $object ex. Greendot\EshopBundle\Entity\Project\Product
     *
     * if given shortcode supports this object returns true
     *
     * @param string $object
     * @return bool
     */
    final public function supports(string $objectName) :bool
    {
        return in_array($objectName, array_keys($this->supportedFields()));
    }

    /**
     * @return void
     * finds regex in content and gets json data
     */
    final function getShortCodeData(){

    }

    final function replace(object $object): object
    {
        foreach ($this->getFields(get_class($object)) as $field) {
            $getter = 'get' . ucfirst($field);
            $setter = 'set' . ucfirst($field);
            $content = $object->$getter();

            preg_match_all($this->regex(), $content, $matches);
            if (!isset($matches)) continue;

            foreach ($matches[0] as $key => $shortCode) {
                $data = null;
                if (isset($matches[1][$key])){
                    $data = $matches[1][$key];
                    $data = json_decode($data);
                    if (!is_null($data)) $data = (array) $data;
                }
                $replacedContent = $this->replaceableContent(get_class($object), $data);
                $content = str_replace($shortCode, $replacedContent, $content);
            }
            $object->$setter($content);
        }
        return $object;
    }


}