<?php

namespace Drupal\php_custom_code\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form to manage PHP code blocks.
 */
class PhpCustomCodeSettingsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'php_custom_code_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Determine the number of blocks to show.
    $num_blocks = $form_state->get('num_blocks');
    if ($num_blocks === NULL) {
      // Load existing blocks from the database.
      $query = \Drupal::database()->select('php_custom_code', 'p')
        ->fields('p', ['id', 'title', 'code', 'enabled', 'global', 'pages']);
      $results = $query->execute()->fetchAll();
      $existing_blocks = [];
      foreach ($results as $result) {
        $existing_blocks[] = $result;
      }
      $num_blocks = count($existing_blocks);
      if ($num_blocks < 1) {
        $num_blocks = 1;
      }
      $form_state->set('existing_blocks', $existing_blocks);
      $form_state->set('num_blocks', $num_blocks);
    }
    else {
      $existing_blocks = $form_state->get('existing_blocks');
    }

    $form['blocks'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#prefix' => '<div id="blocks-wrapper">',
      '#suffix' => '</div>',
    ];

    // Build each code block item.
    for ($i = 0; $i < $num_blocks; $i++) {
      $block = isset($existing_blocks[$i]) ? $existing_blocks[$i] : NULL;
      $form['blocks'][$i] = [
        '#type' => 'fieldset',
        '#title' => $block ? $block->title : $this->t('New PHP Code Block'),
      ];
      if ($block && !empty($block->id)) {
        $form['blocks'][$i]['id'] = [
          '#type' => 'hidden',
          '#value' => $block->id,
        ];
      }
      $form['blocks'][$i]['title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Title'),
        '#default_value' => $block ? $block->title : '',
        '#required' => TRUE,
      ];
      $form['blocks'][$i]['enabled'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enabled'),
        '#default_value' => $block ? $block->enabled : 0,
      ];
      $form['blocks'][$i]['global'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Global (execute on all pages)'),
        '#default_value' => $block ? $block->global : 1,
      ];
      $form['blocks'][$i]['pages'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Pages (comma-separated)'),
        '#default_value' => $block ? $block->pages : '',
        '#description' => $this->t('Specify pages (Drupal path patterns) on which to execute the code. Ignored if Global is checked.'),
        '#states' => [
          'visible' => [
            ':input[name="blocks[' . $i . '][global]"]' => ['checked' => FALSE],
          ],
        ],
      ];
      $form['blocks'][$i]['code'] = [
        '#type' => 'textarea',
        '#title' => $this->t('PHP Code'),
        '#default_value' => $block ? $block->code : '',
        '#description' => $this->t('Enter PHP code to be executed. DO NOT include the <?php ?> tags.'),
        '#rows' => 10,
      ];
      // Add a checkbox to allow deletion of this block.
      $form['blocks'][$i]['remove'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Delete this block'),
        '#default_value' => 0,
      ];
    }

    $form['add_block'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another PHP Code Block'),
      '#submit' => ['::addOne'],
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'wrapper' => 'blocks-wrapper',
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
    ];

    return $form;
  }

  /**
   * AJAX callback for the "Add another PHP Code Block" button.
   */
  public function ajaxCallback(array &$form, FormStateInterface $form_state) {
    return $form['blocks'];
  }

  /**
   * Submit handler for the add block button.
   */
  public function addOne(array &$form, FormStateInterface $form_state) {
    $num_blocks = $form_state->get('num_blocks');
    $num_blocks++;
    $form_state->set('num_blocks', $num_blocks);
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $blocks = $form_state->getValue('blocks');
    $connection = \Drupal::database();

    // Process each code block.
    foreach ($blocks as $block) {
      // If the block is marked for removal, delete it if exists and skip further processing.
      if (!empty($block['remove'])) {
        if (!empty($block['id'])) {
          $connection->delete('php_custom_code')
            ->condition('id', $block['id'])
            ->execute();
        }
        continue;
      }

      $data = [
        'title' => $block['title'],
        'code' => $block['code'],
        'enabled' => $block['enabled'] ? 1 : 0,
        'global' => $block['global'] ? 1 : 0,
        'pages' => $block['pages'],
        'changed' => \Drupal::time()->getRequestTime(),
      ];

      if (!empty($block['id'])) {
        // Update an existing block.
        $connection->update('php_custom_code')
          ->fields($data)
          ->condition('id', $block['id'])
          ->execute();
      }
      else {
        // Insert a new block.
        $data['created'] = \Drupal::time()->getRequestTime();
        $connection->insert('php_custom_code')
          ->fields($data)
          ->execute();
      }
    }

    $this->messenger()->addStatus($this->t('PHP Code Blocks configuration saved.'));
  }

}

