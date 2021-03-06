<?php

namespace Drupal\odp_user;

use Drupal\group\GroupMembership;
use Drupal\Core\Database\Connection;
use Drupal\group\Entity\GroupInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\odp_program\Program;
use Drupal\odp_program\Utility\ProgramUtility;
use Drupal\views\Views;
use Drupal\Core\Path\AliasManagerInterface;
use Drupal\odp_domain\Program\ProgramDomainInterface;

/**
 * Class Organisation.
 *
 * @package Drupal\odp_user
 */
class Organisation {

  use LoggerChannelTrait;


  /**
   * Current user organisation.
   *
   * @var mixed
   */
  protected $organisation;

  /**
   * Current user program.
   *
   * @var mixed
   */
  protected $program;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The path alias manager.
   *
   * @var \Drupal\Core\Path\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * ProgramDomain Interface.
   *
   * @var Drupal\odp_domain\Program\ProgramDomainInterface
   */
  protected $programDomain;

  /**
   * Pass the dependency to the object constructor.
   */
  public function __construct(
    PrivateTempStoreFactory $temp_store,
    Connection $connection,
    AccountInterface $account,
    EntityTypeManagerInterface $entity_type_manager,
    AliasManagerInterface $alias_manager,
    ProgramDomainInterface $program_domain,
    Program $program) {
    $this->account = $account;
    $this->organisation = $temp_store->get('odp_user');
    $this->program = $program;
    $this->connection = $connection;
    $this->entityTypeManager = $entity_type_manager;
    $this->aliasManager = $alias_manager;
    $this->programDomain = $program_domain;
  }

  /**
   * Set the organisation id.
   *
   * @param int $value
   *   Organisation id for current user.
   */
  public function setProgramId($value) {
    $this->organisation->set('org_id', (int) $value);
    return $this;
  }

  /**
   * Get the organisation id.
   *
   * @return int
   *   Organisation ID.
   */
  public function getOrgId() {
    return $this->organisation->get('org_id') ?: 0;
  }

  /**
   * Function to get user's organisation.
   *
   * @return mixed
   *   User organisation.
   */
  public function getUserOrganisations() {
    try {
      return $this->program->getProgramData();
    }
    catch (\Exception $e) {
      $logger = $this->getLogger('devportal-user-block');
      $logger->error($e->getMessage());
    }
  }

  /**
   * Function to get organization roles.
   *
   * @param bool $strict
   *   Boolean param to specify strict check.
   *
   * @return array
   *   Return roles array.
   */
  public function getAdminRoles($strict = FALSE) {
    if ($strict === FALSE) {
      return [
        'admin',
        'product_manager',
      ];
    }
    return [
      'admin',
    ];
  }

  /**
   * Get user's role in a group.
   *
   * @param int $group_id
   *   Group id.
   *
   * @return array
   *   Return array of roles in a group.
   */
  public function getUserGroupRole($group_id = NULL) {
    $group_roles = [];

    if (!$group_id) {
      $group_id = $this->getOrgId();
    }
    $group = $this->entityTypeManager->getStorage('group')->load($group_id);
    if ($group instanceof GroupInterface) {
      $member = $group->getMember($this->account);
      if ($member instanceof GroupMembership) {
        foreach ($member->getRoles() as $grole) {
          if ($grole->isInternal() == FALSE) {
            $group_roles[] = $grole->id();
          }
        }
      }
    }

    return $group_roles;
  }

  /**
   * Check user access, if user is visible or not.
   */
  public function checkAccess($check_pm = FALSE) {
    $diff = array_intersect(ProgramInterface::ADMIN_ROLES, $this->account->getRoles());
    if (count($diff) > 0) {
      return TRUE;
    }

    if ($check_pm) {
      $roles = array_intersect(ProgramInterface::PM_ROLES, $this->getUserGroupRole($this->getOrgId()));
      if (count($roles) > 0) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Function to get organisation information.
   *
   * @return array
   *   Organisation data.
   */
  public function getUserProgramsData() {
    // Displaying user's own program in my account section.
    $view = Views::getView("programs");
    $view->setDisplay("user_programs");
    $view->execute();
    if (!empty($view)) {
      $programs = [];
      foreach ($view->result as $value) {
        try {
          $gid = $value->_entity->get('id')->value;
          $programs[$gid]['programId'] = $gid;
          $programs[$gid]['programName'] = $value->_entity->get('label')->value;
          if (!empty($value->_entity->get('field_program_image'))) {
            $image = $value->_entity->get('field_program_image')->getValue();
            if ($image[0]) {
              $data = !empty($image[0]['target_id']) ? ProgramUtility::getImageUri($image[0]['target_id']) : "";
            }
            $programs[$gid]['programImage'] = !empty($data) ?
              ProgramUtility::generateOrganisationImage($data) : "";
          }
          $programs[$gid]['orgPath'] = !empty($gid) ?
          "/dashboard/save-program/$gid" : "#";

          $programs[$gid]['programUrl'] = $this->aliasManager->getAliasByPath("/group/$gid");
          $programs[$gid]['target'] = "_self";
          $group_domain = $this->entityTypeManager->getStorage('domain')->load('group_' . $gid);
          if ($group_domain) {
            $programs[$gid]['programUrl'] = $group_domain->getPath();
            $programs[$gid]['target'] = "_blank";
          }
        }
        catch (\Exception $e) {
          $logger = $this->getLogger('developer-portal-user');
          $logger->error($e->getMessage());
        }
      }

      if ($program_id = $this->programDomain->getProgramDomainId()) {
        if ($programs[$program_id]) {
          $program[$program_id] = $programs[$program_id];
          return $program;
        }
      }
      return $programs;
    }
  }

}
