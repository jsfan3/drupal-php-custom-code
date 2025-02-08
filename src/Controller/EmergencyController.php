<?php

namespace Drupal\php_custom_code\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for emergency actions.
 */
class EmergencyController extends ControllerBase {

  /**
   * Disables all PHP code blocks.
   */
  public function disableAll() {
    $connection = \Drupal::database();
    $connection->update('php_custom_code')
      ->fields([
        'enabled' => 0,
        'changed' => \Drupal::time()->getRequestTime(),
      ])
      ->execute();

    $this->messenger()->addWarning($this->t('All PHP code blocks have been disabled for safety.'));
    return new RedirectResponse(Url::fromRoute('php_custom_code.settings')->toString());
  }

}

