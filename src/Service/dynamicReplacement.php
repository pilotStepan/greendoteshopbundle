<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Repository\Project\CategoryRepository;

class dynamicReplacement
{
    public function __construct(CategoryRepository $categoryRepository)
    {
        $this->categoryRepository = $categoryRepository;
    }
    public function dynamicCategoryReplace($html){
        $tagStart = "@@category-";
        $tagEnd = "@@";
        while(strpos($html, $tagStart)){
            $categoryPos = strpos($html, $tagStart);
            $categoryPos += strlen($tagStart);
            $contentLen = strpos($html, $tagEnd, $categoryPos) - $categoryPos;
            $categoryID = substr($html, $categoryPos, $contentLen);
            $tag = $tagStart.$categoryID.$tagEnd;
            $category = $this->categoryRepository->findOneBy(['id' => $categoryID]);
            if ($category) {
                $html = str_replace($tag, $category->getHtml(), $html);
            } else {
                $html = str_replace($tag, "", $html);
            }
        }
        return $html;
    }
}