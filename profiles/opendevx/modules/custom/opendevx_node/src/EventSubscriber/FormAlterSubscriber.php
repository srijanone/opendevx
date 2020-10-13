<?php

namespace Drupal\opendevx_node\EventSubscriber;

use Drupal\core_event_dispatcher\Event\Form\FormAlterEvent;
use Drupal\hook_event_dispatcher\HookEventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Form alter event subscriber class.
 */
class FormAlterSubscriber implements EventSubscriberInterface {

  /**
   * @var mixed $currentPath
   */
  protected $currentPath;

  /**
   * Object EntityTypeManager.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Form Alter constructor.
   *
   * @param mixed $entity_type_manager
   *   The EntityTypeManagerInterface.
   * @param mixed $request_stack
   *   The plugin request stack service.
   */
  public function __construct(RequestStack $request_stack, EntityTypeManagerInterface $entity_type_manager) {
    $this->currentPath = $request_stack;
    $this->entityTypeManager = $entity_type_manager;
  }



  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      HookEventDispatcherInterface::FORM_ALTER => 'alterForm',
    ];
  }

  /**
   * Alter form.
   *
   * @param FormAlterEvent $event
   *   The event.
   */
  public function alterForm(FormAlterEvent $event): void {
    $form = &$event->getForm();
    $destination = explode("/", $this->currentPath->getCurrentRequest()->query->get('destination'));
    $group_forms = [
      'group_private_add_form',
      'group_protected_add_form',
      'group_public_add_form'
    ];
    $group_member_forms = [
      'group_content_public-group_membership_add_form', 'group_content_public-group_membership_edit_form',
      'group_content_private-group_membership_add_form', 'group_content_private-group_membership_edit_form',
      'group_content_protected-group_membership_add_form', 'group_content_protected-group_membership_edit_form'
    ];
    if (in_array($form['#form_id'], $group_forms)) {
      $form['actions']['submit']['#value'] = t('Save');
    }
    else if (in_array($form['#form_id'], $group_member_forms)) {
      // Make the role button required and radio.
      $form['group_roles']['widget']['#type'] = 'radios';
      $form['group_roles']['widget']['#required'] = TRUE;
      $form['group_roles']['widget']['#default_value'] = $form['group_roles']['widget']['#default_value'][0];
    }
    $route = $this->currentPath->getCurrentRequest()->attributes->get('_route');
    if (!empty($destination[5]) && (in_array($route, ["entity.node.edit_form", "entity.group_content.create_form"]))) {
      if ($route == "entity.group_content.create_form") {
        $form['field_api_product']['#access'] = FALSE;
      }
      $form['field_api_product']['widget'][0]['target_id']['#default_value'] = $this->entityTypeManager->getStorage('node')->load($destination[5]);
    }
  }

}