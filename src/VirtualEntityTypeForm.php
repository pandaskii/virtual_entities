<?php

namespace Drupal\virtual_entities;

use Drupal\Core\Entity\BundleEntityFormBase;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for virtual entity type forms.
 */
class VirtualEntityTypeForm extends BundleEntityFormBase {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * Constructs the NodeTypeForm object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   */
  public function __construct(EntityManagerInterface $entity_manager) {
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $type = $this->entity;
    if ($this->operation == 'add') {
      $form['#title'] = $this->t('Add virtual entity type');
      $fields = $this->entityManager->getBaseFieldDefinitions('virtual_entity');
      // Create a node with a fake bundle using the type's UUID so that we can
      // get the default values for workflow settings.
      // @todo Make it possible to get default values without an entity.
      //   https://www.drupal.org/node/2318187
      $node = $this->entityManager->getStorage('virtual_entity')->create(['type' => $type->uuid()]);
    }
    else {
      $form['#title'] = $this->t('Edit %label virtual entity type', ['%label' => $type->label()]);
      $fields = $this->entityManager->getFieldDefinitions('virtual_entity', $type->id());
      // Create a node to get the current values for workflow settings fields.
      $node = $this->entityManager->getStorage('virtual_entity')->create(['type' => $type->id()]);
    }

    $form['label'] = [
      '#title' => t('Name'),
      '#type' => 'textfield',
      '#default_value' => $type->label(),
      '#description' => t('The human-readable name of this content type. This name must be unique.'),
      '#required' => TRUE,
      '#size' => 30,
    ];

    $form['type'] = [
      '#type' => 'machine_name',
      '#default_value' => $type->id(),
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#disabled' => $type->isLocked(),
      '#machine_name' => [
        'exists' => ['Drupal\virtual_entities\Entity\VirtualEntityType', 'load'],
        'source' => ['label'],
      ],
      '#description' => t('A unique machine-readable name for this content type. It must only contain lowercase letters, numbers, and underscores.'),
    ];

    $form['description'] = [
      '#title' => t('Description'),
      '#type' => 'textarea',
      '#default_value' => $type->getDescription(),
      '#description' => t('Virtual entity type description.'),
    ];

    $form['endpoint'] = [
      '#title' => t('Endpoint'),
      '#type' => 'textfield',
      '#default_value' => $type->getEndpoint(),
      '#description' => t('Virtual entity endpoint.'),
      '#required' => TRUE,
    ];

    $form['additional_settings'] = array(
      '#type' => 'vertical_tabs',
      '#attached' => array(
        'library' => array('node/drupal.content_types'),
      ),
    );

    return $this->protectBundleIdElement($form);
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = t('Save virtual entity type');
    $actions['delete']['#value'] = t('Delete virtual entity type');

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $type = $this->entity;
    $type->set('type', trim($type->id()));
    $type->set('label', trim($type->label()));

    $status = $type->save();

    $t_args = ['%name' => $type->label()];

    if ($status == SAVED_UPDATED) {
      drupal_set_message(t('The virtual entity type %name has been updated.', $t_args));
    }
    elseif ($status == SAVED_NEW) {
      drupal_set_message(t('The virtual entity type %name has been added.', $t_args));
      $context = array_merge($t_args, ['link' => $type->link($this->t('View'), 'collection')]);
      $this->logger('virtual_entity')->notice('Added virtual entity type %name.', $context);
    }

    $this->entityManager->clearCachedFieldDefinitions();
    $form_state->setRedirectUrl($type->urlInfo('collection'));
  }

}
