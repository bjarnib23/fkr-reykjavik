<?php

namespace Drupal\fkr_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Exposes FKR page content as JSON for the React frontend.
 */
class ContentController extends ControllerBase {

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * Admin list of all page_content nodes.
   */
  public function adminList(): array {
    $nids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'page_content')
      ->sort('title', 'ASC')
      ->accessCheck(FALSE)
      ->execute();

    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);
    $rows = [];

    foreach ($nodes as $node) {
      $rows[] = [
        $node->getTitle(),
        $node->get('field_page_subtitle')->value ?? '—',
        $node->isPublished() ? 'Published' : 'Unpublished',
        \Drupal\Core\Markup::create(
          $node->toLink('Edit', 'edit-form')->toString()
        ),
      ];
    }

    return [
      'add_button' => [
        '#type'       => 'link',
        '#title'      => '+ Bæta við síðu',
        '#url'        => \Drupal\Core\Url::fromRoute('entity.node.add_form', ['node_type' => 'page_content']),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ],
      'table' => [
        '#type'   => 'table',
        '#header' => ['Title', 'Subtitle', 'Status', 'Edit'],
        '#rows'   => $rows,
        '#empty'  => 'No page content yet.',
      ],
    ];
  }

  /**
   * GET /api/fkr/pages
   * Returns all page content nodes as { page_title: { fields } }.
   */
  public function pages(): JsonResponse {
    $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type'   => 'page_content',
      'status' => 1,
    ]);

    $result = [];
    foreach ($nodes as $node) {
      $result[$node->getTitle()] = [
        'title'      => $node->getTitle(),
        'body_text'  => $node->get('field_body_text')->value,
        'subtitle'   => $node->get('field_page_subtitle')->value,
        'cta_text'   => $node->get('field_cta_text')->value,
        'images'     => $this->getImageUrls($node, 'field_page_image'),
        'slug'       => $node->get('field_slug')->value,
      ];
    }

    return new JsonResponse($result, 200, $this->cors());
  }

  /**
   * GET /api/fkr/faq
   */
  public function faq(): JsonResponse {
    $nids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'fkr_faq')
      ->condition('status', 1)
      ->sort('field_faq_weight', 'ASC')
      ->accessCheck(FALSE)
      ->execute();

    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);
    $items = [];

    foreach ($nodes as $node) {
      $items[] = [
        'question' => $node->getTitle(),
        'answer'   => $node->get('field_answer')->value,
      ];
    }

    $pageNodes = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type'       => 'page_content',
      'field_slug' => 'faq',
      'status'     => 1,
    ]);
    $pageNode = reset($pageNodes);
    $pageTitle = $pageNode ? $pageNode->get('field_page_subtitle')->value : '';

    return new JsonResponse([
      'page_title' => $pageTitle,
      'items'      => $items,
    ], 200, $this->cors());
  }

  /**
   * GET /api/fkr/pricelist
   * Returns all price rows sorted by weight, with ordered grade columns.
   */
  public function pricelist(): JsonResponse {
    $grades = ['aa','a','b','bb','c','d','e','f','g','gg','h','hh','i','j','jj','k','l','r'];

    $nids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'fkr_price_item')
      ->condition('status', 1)
      ->sort('field_weight', 'ASC')
      ->accessCheck(FALSE)
      ->execute();

    if (empty($nids)) {
      return new JsonResponse([], 200, $this->cors());
    }

    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);
    $rows = [];

    foreach ($nodes as $node) {
      $prices = [];
      foreach ($grades as $grade) {
        $field = 'field_price_' . $grade;
        $prices[$grade] = $node->hasField($field) && !$node->get($field)->isEmpty()
          ? (int) $node->get($field)->value
          : null;
      }
      $rows[] = [
        'item'   => $node->getTitle(),
        'prices' => $prices,
      ];
    }

    return new JsonResponse([
      'grades' => array_map('strtoupper', $grades),
      'rows'   => $rows,
    ], 200, $this->cors());
  }

  /**
   * GET /api/fkr/settings
   */
  public function settings(): JsonResponse {
    $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type'   => 'site_settings',
      'status' => 1,
    ]);

    if (empty($nodes)) {
      return new JsonResponse([], 200, $this->cors());
    }

    $node = reset($nodes);

    $logoUrl = '';
    $logoField = $node->get('field_logo');
    if (!$logoField->isEmpty()) {
      $media = $logoField->first()->get('entity')->getTarget()?->getValue();
      if ($media) {
        $imageField = $media->get('field_media_image');
        if (!$imageField->isEmpty()) {
          $file = $imageField->first()->get('entity')->getTarget()?->getValue();
          if ($file) {
            $logoUrl = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
          }
        }
      }
    }

    return new JsonResponse([
      'logo'           => $logoUrl,
      'phone'          => $node->get('field_site_phone')->value,
      'address'        => $node->get('field_site_address')->value,
      'company_id'     => $node->get('field_company_id')->value,
      'footer_heading' => $node->get('field_footer_heading')->value,
    ], 200, $this->cors());
  }

  /**
   * GET /api/fkr/services
   */
  public function services(): JsonResponse {
    $nids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'thjonusta')
      ->condition('status', 1)
      ->sort('field_weight', 'ASC')
      ->accessCheck(FALSE)
      ->execute();

    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);
    $items = [];

    foreach ($nodes as $node) {
      $imageUrl = '';
      $mediaField = $node->get('field_mynd');
      if (!$mediaField->isEmpty()) {
        $media = $mediaField->first()->get('entity')->getTarget()?->getValue();
        if ($media) {
          $imageField = $media->get('field_media_image');
          if (!$imageField->isEmpty()) {
            $file = $imageField->first()->get('entity')->getTarget()?->getValue();
            if ($file) {
              $imageUrl = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
            }
          }
        }
      }
      $items[] = [
        'id'    => $node->id(),
        'title' => $node->getTitle(),
        'desc'  => $node->get('field_lysing')->value,
        'image' => $imageUrl,
      ];
    }

    return new JsonResponse($items, 200, $this->cors());
  }

  /**
   * Resolves an image field to an array of absolute URLs.
   */
  private function getImageUrls($node, string $field_name): array {
    $field = $node->get($field_name);
    if ($field->isEmpty()) {
      return [];
    }

    $urls = [];
    foreach ($field as $item) {
      $file = $item->get('entity')->getTarget()?->getValue();
      if ($file) {
        $urls[] = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
      }
    }
    return $urls;
  }

  private function cors(): array {
    return [
      'Access-Control-Allow-Origin'  => '*',
      'Access-Control-Allow-Methods' => 'GET',
      'Access-Control-Allow-Headers' => 'Content-Type',
    ];
  }

}
