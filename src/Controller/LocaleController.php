<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LocaleController extends AbstractController
{
    #[Route('/change-locale/{locale}', name: 'change_locale')]
    public function changeLocale(string $locale, Request $request): Response
    {
        // 设置会话中的语言
        $request->getSession()->set('_locale', $locale);

        // 获取引用页面
        $referer = $request->headers->get('referer');

        // 如果没有引用页面，返回首页
        if (!$referer) {
            return $this->redirectToRoute('app_shop');
        }

        return $this->redirect($referer);
    }
}
