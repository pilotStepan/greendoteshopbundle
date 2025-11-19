<?php

namespace Greendot\EshopBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Greendot\EshopBundle\I18n\RouteTranslator;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class LocaleController extends AbstractController
{
    #[Route(
        '/_locale/switch',
        name: 'app_switch_locale',
        methods: ['GET'],
        priority: 100,
    )]
    public function switchLocale(Request $request, RouteTranslator $routeTranslator): RedirectResponse
    {
        $targetLocale = $request->query->get('locale');
        $referer = $request->headers->get('referer');

        $enabledLocales = ['cs', 'sk'];
        if (!in_array($targetLocale, $enabledLocales)) {
            throw $this->createNotFoundException('Locale not supported');
        }

        if (!$referer) {
            return $this->redirectToRoute('web_homepage', ['_locale' => $targetLocale]);
        }

        $newUrl = $routeTranslator->getTranslatedUrl($referer, $targetLocale);

        return new RedirectResponse($newUrl);
    }
}