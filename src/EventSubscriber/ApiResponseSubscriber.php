<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiResponseSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onResponse'];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')
            || !$response instanceof JsonResponse || $response->getStatusCode() < 400) {
            return;
        }
        $data = json_decode($response->getContent(), true);
        if (is_array($data) && isset($data['message']) && is_string($data['message']) && !isset($data['error'])) {
            $event->setResponse(new JsonResponse(['error' => $data['message']], $response->getStatusCode(), $response->headers->all()));
        }
    }
}
