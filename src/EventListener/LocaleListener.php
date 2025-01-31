<?php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;

class LocaleListener
{
    private $defaultLocale;

    public function __construct(string $defaultLocale = 'zh')
    {
        $this->defaultLocale = $defaultLocale;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $request = $event->getRequest();

        // 从会话中获取语言设置
        if ($locale = $request->getSession()->get('_locale')) {
            $request->setLocale($locale);
        } else {
            $request->setLocale($this->defaultLocale);
        }
    }
}
