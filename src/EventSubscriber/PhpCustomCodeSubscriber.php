<?php

namespace Drupal\php_custom_code\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscribes to the kernel request event to execute custom PHP code blocks.
 */
class PhpCustomCodeSubscriber implements EventSubscriberInterface {

  /**
   * Executes PHP code blocks on request.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function onKernelRequest(RequestEvent $event) {
    // Only process the main (master) request.
    if (!$event->isMainRequest()) {
      return;
    }

    $request = $event->getRequest();
    $current_path = $request->getPathInfo();

    $connection = \Drupal::database();
    $query = $connection->select('php_custom_code', 'p')
      ->fields('p', ['code', 'global', 'pages', 'enabled']);
    // Enforce execution order by id (ascending).
    $query->orderBy('id', 'ASC');
    $results = $query->execute()->fetchAll();

    foreach ($results as $block) {
      if (!$block->enabled) {
        continue;
      }

      if ($block->global) {
        // Execute the code block on all pages.
        $this->executeCode($block->code);
      }
      else {
        // Execute only if the current path matches one of the specified pages.
        $pages = array_map('trim', explode(',', $block->pages));
        foreach ($pages as $page) {
          if (!empty($page) && strpos($current_path, $page) !== FALSE) {
            $this->executeCode($block->code);
            break;
          }
        }
      }
    }
  }

  /**
   * Executes the given PHP code.
   *
   * @param string $code
   *   The PHP code to execute.
   */
  protected function executeCode($code) {
    // IMPORTANT: Executing arbitrary PHP code via eval() is dangerous.
    // The try/catch below may not catch fatal errors due to syntax issues.
    try {
      if (substr(trim($code), -1) !== ';') {
        $code .= ';';
      }
      eval($code);
    }
    catch (\Throwable $e) {
      \Drupal::logger('php_custom_code')->error('Error executing custom code: @message', ['@message' => $e->getMessage()]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['onKernelRequest'];
    return $events;
  }

}
