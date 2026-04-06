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
        'body_text'  => $node->get('field_body_text')->value,
        'subtitle'   => $node->get('field_page_subtitle')->value,
        'cta_text'   => $node->get('field_cta_text')->value,
        'image'      => $this->getImageUrl($node, 'field_page_image'),
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

    return new JsonResponse($items, 200, $this->cors());
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
   * Resolves an image field to an absolute URL.
   */
  private function getImageUrl($node, string $field_name): string {
    $field = $node->get($field_name);
    if ($field->isEmpty()) {
      return '';
    }

    $file = $field->first()->get('entity')->getTarget()?->getValue();
    if (!$file) {
      return '';
    }

    return \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
  }

  private function cors(): array {
    return [
      'Access-Control-Allow-Origin'  => '*',
      'Access-Control-Allow-Methods' => 'GET',
      'Access-Control-Allow-Headers' => 'Content-Type',
    ];
  }

}
