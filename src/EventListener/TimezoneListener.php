<?php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;

class TimezoneListener
{
    private $timezone;

    public function __construct(string $timezone)
    {
        $this->timezone = $timezone;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        try {
            date_default_timezone_set($this->timezone);
        } catch (\Exception $e) {
            // 如果时区设置失败，使用偏移量
            date_default_timezone_set('+0200');
        }
    }
}
