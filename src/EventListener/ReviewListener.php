<?php

namespace Greendot\EshopBundle\EventListener;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Greendot\EshopBundle\Entity\Project\Review;
use Greendot\EshopBundle\Repository\Project\ReviewPointsRepository;

class ReviewListener
{
    private $reviewPointsRepository;

    public function __construct(ReviewPointsRepository $reviewPointsRepository)
    {
        $this->reviewPointsRepository = $reviewPointsRepository;
    }

    public function postLoad(LifecycleEventArgs $event): void
    {
        $entity = $event->getObject();

        if (!$entity instanceof Review) {
            return;
        }

        $reviewPoints = $this->reviewPointsRepository->findBy(['review_id' => $entity->getId()]);
        $entity->setReviewPoints($reviewPoints);
    }
}
