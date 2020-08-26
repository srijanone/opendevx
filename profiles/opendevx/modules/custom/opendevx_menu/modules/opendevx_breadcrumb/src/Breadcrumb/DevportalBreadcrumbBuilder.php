<?php

namespace Drupal\opendevx_breadcrumb\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\opendevx_breadcrumb\Utility\BreadcrumbUtility;
use Drupal\Core\Link;
use Drupal\Core\Path\CurrentPathStack;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;

/**
 * Class DevportalBreadcrumbBuilder.
 */
class DevportalBreadcrumbBuilder implements BreadcrumbBuilderInterface, ContainerInjectionInterface {
  /**
   * Breadcrumb utility variable.
   *
   * @var \Drupal\opendevx_breadcrumb\Utility\BreadcrumbUtility
   */
  protected $breadcrumbUtility;

  /**
   * Represents the current path for the current request.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $pathCurrent;
  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Node type.
   *
   * @var nodeType
   */
  protected $nodeType;

  /**
   * Constructor function.
   *
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   *   The current path stack.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\opendevx_breadcrumb\Utility\BreadcrumbUtility $breadcrumb_utility
   *   The breadcrumb utility.
   */
  public function __construct(CurrentPathStack $current_path,
   RequestStack $request_stack,
   BreadcrumbUtility $breadcrumb_utility) {
    $this->breadcrumbUtility = $breadcrumb_utility;
    $this->pathCurrent = $current_path;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('path.current')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $attributes) {
    $return = FALSE;
    if ($attributes) {
      $parameters = $attributes->getParameters()->all();
      $this->breadcrumbUtility->setNodeByPath();
      $this->nodeType = isset($parameters['node']) ?
       $parameters['node']->bundle() :
       $this->breadcrumbUtility->getNode('bundle');
    }
    // Check if the current path is a node/detail page.
    // Breadcrumb is displayed on the detail page which have parent parameter.
    if ((!empty($parameters['node']) &&
     !empty($this->requestStack->getCurrentRequest()->get('parent'))) || $this->nodeType == 'api_product') {
      $return = TRUE;
    }
    elseif (isset($parameters['view_id'])) {
      $views_id = [
        'proxies_listing',
        'organisation_users',
        'dashboard_listing',
      ];
      if (in_array($parameters['view_id'], $views_id)) {
        $return = TRUE;
      }
    }
    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $parameters = $route_match->getParameters()->all();
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addLink(Link::createFromRoute('Home', '<front>'));
    if ((!empty($parameters['node']) &&
     !empty($this->requestStack->getCurrentRequest()->get('parent'))) || $this->nodeType == 'api_product') {
      // Breadcrumb for node.
      $breadcrumb = $this->breadcrumbUtility->getNodeBreadcrumb($breadcrumb, $parameters);
    }
    elseif (isset($parameters['view_id'])) {
      // Breadcrumb for views.
      $breadcrumb = $this->breadcrumbUtility->getViewBreadcrumb($breadcrumb, $parameters);
    }

    return $breadcrumb;
  }

}