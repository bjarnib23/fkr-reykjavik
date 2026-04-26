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
        'cta_text'        => $node->get('field_cta_text')->value,
        'primary_button'   => $node->get('field_primary_button')->value,
        'secondary_button' => $node->get('field_secondary_button')->value,
        'label_name'          => $node->get('field_label_name')->value,
        'label_phone'         => $node->get('field_label_phone')->value,
        'label_email'         => $node->get('field_label_email')->value,
        'label_service'       => $node->get('field_label_service')->value,
        'label_date'          => $node->get('field_label_date')->value,
        'label_time'          => $node->get('field_label_time')->value,
        'label_notes'         => $node->get('field_label_notes')->value,
        'placeholder_notes'   => $node->get('field_placeholder_notes')->value,
        'success_heading'     => $node->get('field_success_heading')->value,
        'success_body'        => $node->get('field_success_body')->value,
        'label_slots'         => $node->get('field_label_slots')->value,
        'label_no_slots'      => $node->get('field_label_no_slots')->value,
        'placeholder_name'    => $node->get('field_placeholder_name')->value,
        'placeholder_phone'   => $node->get('field_placeholder_phone')->value,
        'placeholder_email'   => $node->get('field_placeholder_email')->value,
        'label_choose_amount' => $node->get('field_label_choose_amount')->value,
        'label_your_details'  => $node->get('field_label_your_details')->value,
        'label_confirm_email' => $node->get('field_label_confirm_email')->value,
        'label_loading'       => $node->get('field_label_loading')->value,
        'label_preview'       => $node->get('field_label_preview')->value,
        'card_title'          => $node->get('field_card_title')->value,
        'card_code_label'     => $node->get('field_card_code_label')->value,
        'card_no_expiry'      => $node->get('field_card_no_expiry')->value,
        'card_note'           => $node->get('field_card_note')->value,
        'err_select_amount'   => $node->get('field_err_select_amount')->value,
        'err_name_required'   => $node->get('field_err_name_required')->value,
        'err_phone_required'  => $node->get('field_err_phone_required')->value,
        'err_email_required'  => $node->get('field_err_email_required')->value,
        'err_invalid_email'   => $node->get('field_err_invalid_email')->value,
        'err_email_mismatch'  => $node->get('field_err_email_mismatch')->value,
        'err_load_amounts'    => $node->get('field_err_load_amounts')->value,
        'err_submit'          => $node->get('field_err_submit')->value,
        'err_payment'         => $node->get('field_err_payment')->value,
        'images'          => $this->getImageUrls($node, 'field_page_image'),
        'slug'            => $node->get('field_slug')->value,
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
      'address'          => $node->get('field_site_address')->value,
      'company_id'       => $node->get('field_company_id')->value,
      'footer_heading'   => $node->get('field_footer_heading')->value,
      'summary_heading'  => $node->get('field_summary_heading')->value,
      'summary_location' => $node->get('field_summary_location')->value,
      'summary_when'     => $node->get('field_summary_when')->value,
      'summary_name'     => $node->get('field_label_name')->value,
      'summary_phone'    => $node->get('field_label_phone')->value,
      'summary_email'    => $node->get('field_label_email')->value,
      'msg_slot_taken'   => $node->get('field_msg_slot_taken')->value,
      'msg_hold_expired' => $node->get('field_msg_hold_expired')->value,
      'msg_submit_error' => $node->get('field_msg_submit_error')->value,
      'msg_hold_countdown' => $node->get('field_msg_hold_countdown')->value,
    ], 200, $this->cors());
  }

  /**
   * GET /api/fkr/nav
   */
  public function nav(): JsonResponse {
    $nids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'page_content')
      ->condition('status', 1)
      ->exists('field_nav_weight')
      ->sort('field_nav_weight', 'ASC')
      ->accessCheck(FALSE)
      ->execute();

    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);
    $items = [];

    foreach ($nodes as $node) {
      $weight = $node->get('field_nav_weight')->value;
      if ($weight === NULL) {
        continue;
      }
      $items[] = [
        'label'  => $node->get('field_nav_label')->value ?: $node->getTitle(),
        'path'   => $node->get('field_nav_path')->value,
        'is_cta' => (bool) $node->get('field_nav_is_cta')->value,
      ];
    }

    return new JsonResponse($items, 200, $this->cors());
  }

  /**
   * GET /api/fkr/process-steps
   */
  public function processSteps(): JsonResponse {
    $nids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'fkr_process_step')
      ->condition('status', 1)
      ->sort('field_weight_process', 'ASC')
      ->accessCheck(FALSE)
      ->execute();

    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);
    $items = [];

    foreach ($nodes as $node) {
      $items[] = [
        'id'          => $node->id(),
        'title'       => $node->getTitle(),
        'description' => $node->get('field_description_process')->value,
      ];
    }

    return new JsonResponse($items, 200, $this->cors());
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
      $items[] = [
        'id'    => $node->id(),
        'title' => $node->getTitle(),
        'desc'  => $node->get('field_lysing')->value,
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
