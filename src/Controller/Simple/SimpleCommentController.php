<?php

namespace Greendot\EshopBundle\Controller\Simple;

use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Doctrine\DoctrineFiltersConfigNames;
use Greendot\EshopBundle\Repository\Project\CommentRepository;
use Greendot\EshopBundle\Service\ManageMails;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/simple/api/comments', name: 'simple_api_comments_')]
class SimpleCommentController extends AbstractController
{
    public function __construct(
        private readonly ManageMails            $manageMails,
        private readonly EntityManagerInterface $em,
        private readonly CommentRepository      $commentRepository,
    ) {}

    /**
     * E-mails the customer that their question has a published reply and records it in the question's extra.
     */
    #[Route('/{id}/send-notification', name: 'send_notification', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function sendNotification(int $id): JsonResponse
    {
        // see draft replies (inactive) too, so a draft gets 422 rather than looking like a missing comment
        $filters = $this->em->getFilters();
        $filterName = DoctrineFiltersConfigNames::CommentIsActiveFilter->value;
        if ($filters->isEnabled($filterName)) {
            $filters->disable($filterName);
        }

        $question = $this->commentRepository->find($id);
        if ($question === null || $question->getComment() !== null) {
            throw $this->createNotFoundException('Question not found');
        }

        $reply = $question->getPublishedAdminReply();
        if ($reply === null) {
            return $this->json(['message' => 'The question has no published reply'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $recipient = $question->getAuthorEmail();
        if ($recipient === null) {
            return $this->json(['message' => 'The question has no e-mail to notify'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $questionUrl = $this->generateUrl(
            'app_comment_detail',
            ['slug' => $question->getSlug()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        $this->manageMails->sendAdviceReplyEmail($question, $reply, $recipient, $questionUrl);

        $question->addNotification($recipient, new \DateTimeImmutable());
        $this->em->flush();

        $notifications = $question->getNotifications();

        return $this->json($notifications[array_key_last($notifications)]);
    }
}
