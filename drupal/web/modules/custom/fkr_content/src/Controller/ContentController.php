<?php

namespace Drupal\fkr_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class ContentController extends ControllerBase {

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'));
  }

  public function adminList(): array {
    $types = ['basic_page', 'booking_page', 'giftcard_page', 'pricelist_page', 'faq_page'];

    $nids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', $types, 'IN')
      ->sort('title', 'ASC')
      ->accessCheck(FALSE)
      ->execute();

    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);
    $rows  = [];

    foreach ($nodes as $node) {
      $subtitle = $node->hasField('field_page_subtitle')
        ? ($node->get('field_page_subtitle')->value ?? '—')
        : '—';

      $rows[] = [
        $node->getTitle(),
        $subtitle,
        $node->isPublished() ? 'Published' : 'Unpublished',
        \Drupal\Core\Render\Markup::create($node->toLink('Edit', 'edit-form')->toString()),
      ];
    }

    return [
      'table' => [
        '#type'   => 'table',
        '#header' => ['Title', 'Subtitle', 'Status', 'Edit'],
        '#rows'   => $rows,
        '#empty'  => 'No page content yet.',
      ],
    ];
  }

  public function addPage(): array {
    $types = $this->entityTypeManager->getStorage('node_type')->loadMultiple([
      'basic_page', 'booking_page', 'giftcard_page', 'pricelist_page', 'faq_page',
    ]);

    return [
      '#theme'   => 'node_add_list',
      '#content' => $types,
    ];
  }

  public function pages(): JsonResponse {
    $storage = $this->entityTypeManager->getStorage('node');
    $types   = ['basic_page', 'booking_page', 'giftcard_page', 'pricelist_page', 'faq_page'];

    $nodes = [];
    foreach ($types as $type) {
      $nodes += $storage->loadByProperties(['type' => $type, 'status' => 1]);
    }

    $result = [];
    foreach ($nodes as $node) {
      $has = fn(string $f) => $node->hasField($f) && !$node->get($f)->isEmpty();
      $val = fn(string $f) => $has($f) ? $node->get($f)->value : NULL;

      $result[$node->getTitle()] = [
        'title'               => $node->getTitle(),
        'subtitle'            => $val('field_page_subtitle'),
        'body_text'           => $val('field_body_text'),
        'cta_text'            => $val('field_cta_text'),
        'primary_button'      => $val('field_primary_button'),
        'secondary_button'    => $val('field_secondary_button'),
        'label_name'          => $val('field_label_name'),
        'label_phone'         => $val('field_label_phone'),
        'label_email'         => $val('field_label_email'),
        'label_service'       => $val('field_label_service'),
        'label_date'          => $val('field_label_date'),
        'label_time'          => $val('field_label_time'),
        'label_notes'         => $val('field_label_notes'),
        'placeholder_notes'   => $val('field_placeholder_notes'),
        'success_heading'     => $val('field_success_heading'),
        'success_body'        => $val('field_success_body'),
        'success_button'      => $val('field_success_button'),
        'label_slots'         => $val('field_label_slots'),
        'label_no_slots'      => $val('field_label_no_slots'),
        'placeholder_name'    => $val('field_placeholder_name'),
        'placeholder_phone'   => $val('field_placeholder_phone'),
        'placeholder_email'   => $val('field_placeholder_email'),
        'label_wishes'        => $val('field_label_wishes'),
        'placeholder_wishes'  => $val('field_placeholder_wishes'),
        'summary_wishes'      => $val('field_summary_wishes'),
        'label_choose_amount' => $val('field_label_choose_amount'),
        'label_your_details'  => $val('field_label_your_details'),
        'label_confirm_email' => $val('field_label_confirm_email'),
        'label_loading'       => $val('field_label_loading'),
        'label_preview'       => $val('field_label_preview'),
        'card_title'          => $val('field_card_title'),
        'card_code_label'     => $val('field_card_code_label'),
        'card_no_expiry'      => $val('field_card_no_expiry'),
        'card_note'           => $val('field_card_note'),
        'err_select_amount'   => $val('field_err_select_amount'),
        'err_name_required'   => $val('field_err_name_required'),
        'err_phone_required'  => $val('field_err_phone_required'),
        'err_email_required'  => $val('field_err_email_required'),
        'err_invalid_email'   => $val('field_err_invalid_email'),
        'err_email_mismatch'  => $val('field_err_email_mismatch'),
        'err_load_amounts'    => $val('field_err_load_amounts'),
        'err_submit'          => $val('field_err_submit'),
        'err_payment'         => $val('field_err_payment'),
        'images'              => $node->hasField('field_page_image') ? $this->getImageUrls($node, 'field_page_image') : [],
        'success_image'       => $node->hasField('field_success_image') && !$node->get('field_success_image')->isEmpty() ? $this->getImageUrls($node, 'field_success_image')[0] ?? NULL : NULL,
        'slug'                => $val('field_slug'),
        'section_label'       => $val('field_section_label'),
        'nav_cards'           => $this->getNavCards($node),
      ];
    }

    return new JsonResponse($result, 200, $this->cors());
  }

  public function faq(): JsonResponse {
    $page_nodes = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type'   => 'faq_page',
      'status' => 1,
    ]);

    if (empty($page_nodes)) {
      return new JsonResponse(['page_title' => '', 'items' => []], 200, $this->cors());
    }

    $page  = reset($page_nodes);
    $items = [];

    foreach ($page->get('field_faq_items') as $item) {
      $paragraph = $item->entity;
      if (!$paragraph) {
        continue;
      }

      $items[] = [
        'question' => $paragraph->get('field_question')->value,
        'answer'   => $paragraph->get('field_answer')->value,
      ];
    }

    return new JsonResponse([
      'page_title' => $page->get('field_page_subtitle')->value ?? '',
      'items'      => $items,
    ], 200, $this->cors());
  }

  public function pricelist(): JsonResponse {
    $page_nodes = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type'   => 'pricelist_page',
      'status' => 1,
    ]);

    if (empty($page_nodes)) {
      return new JsonResponse([], 200, $this->cors());
    }

    $page   = reset($page_nodes);
    $grades = [];
    $rows   = [];

    foreach ($page->get('field_price_items') as $item) {
      $paragraph = $item->entity;
      if (!$paragraph) {
        continue;
      }

      $service = $paragraph->get('field_service')->value;
      $prices  = [];

      foreach ($paragraph->get('field_price_entries') as $entry_item) {
        $entry = $entry_item->entity;
        if (!$entry) {
          continue;
        }

        $grade = $entry->get('field_grade')->value;
        $price = (int) $entry->get('field_price')->value;

        if (!in_array($grade, $grades)) {
          $grades[] = $grade;
        }

        $prices[$grade] = $price;
      }

      $rows[] = [
        'item'   => $service,
        'prices' => $prices,
      ];
    }

    return new JsonResponse([
      'grades' => $grades,
      'rows'   => $rows,
    ], 200, $this->cors());
  }

  public function settings(): JsonResponse {
    $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type'   => 'site_settings',
      'status' => 1,
    ]);

    if (empty($nodes)) {
      return new JsonResponse([], 200, $this->cors());
    }

    $node    = reset($nodes);

    $getLogoUrl = function(string $fieldName) use ($node): string {
      $field = $node->get($fieldName);
      if ($field->isEmpty()) return '';
      $media = $field->first()->get('entity')->getTarget()?->getValue();
      if (!$media) return '';
      $file = $media->get('field_media_image')->first()?->get('entity')->getTarget()?->getValue();
      return $file ? \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri()) : '';
    };

    return new JsonResponse([
      'navbar_logo'        => $getLogoUrl('field_logo'),
      'footer_logo'        => $getLogoUrl('field_footer_logo'),
      'phone'              => $node->get('field_site_phone')->value,
      'address'            => $node->get('field_site_address')->value,
      'company_id'         => $node->get('field_company_id')->value,
      'footer_heading'     => $node->get('field_footer_heading')->value,
      'summary_heading'    => $node->get('field_summary_heading')->value,
      'summary_location'   => $node->get('field_summary_location')->value,
      'summary_when'       => $node->get('field_summary_when')->value,
      'summary_name'       => $node->get('field_label_name')->value,
      'summary_phone'      => $node->get('field_label_phone')->value,
      'summary_email'      => $node->get('field_label_email')->value,
      'msg_slot_taken'     => $node->get('field_msg_slot_taken')->value,
      'msg_hold_expired'   => $node->get('field_msg_hold_expired')->value,
      'msg_submit_error'   => $node->get('field_msg_submit_error')->value,
      'msg_hold_countdown'   => $node->get('field_msg_hold_countdown')->value,
      'footer_studio_label'  => $node->get('field_footer_studio_label')->value,
      'footer_contact_label' => $node->get('field_footer_contact_label')->value,
      'footer_nav_label'     => $node->get('field_footer_nav_label')->value,
      'social_facebook'      => $node->get('field_social_facebook')->value,
      'social_instagram'     => $node->get('field_social_instagram')->value,
      'social_tiktok'        => $node->get('field_social_tiktok')->value,
      'footer_phone_label'   => $node->get('field_footer_phone_label')->value,
      'footer_email_label'   => $node->get('field_footer_email_label')->value,
      'email'                => $node->get('field_site_email')->value,
      'copyright_year'       => '© ' . date('Y') . ', ',
      'company_name'         => $node->get('field_company_name')->value,
      'navbar_cta_label'     => $node->get('field_navbar_cta_label')->value,
    ], 200, $this->cors());
  }

  public function nav(): JsonResponse {
    $nids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', ['basic_page', 'booking_page', 'giftcard_page', 'pricelist_page', 'faq_page'], 'IN')
      ->condition('status', 1)
      ->exists('field_nav_weight')
      ->sort('field_nav_weight', 'ASC')
      ->accessCheck(FALSE)
      ->execute();

    $items = [];
    foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($nids) as $node) {
      if ($node->get('field_nav_weight')->value === NULL) {
        continue;
      }
      $items[] = [
        'label'  => $node->get('field_nav_label')->value ?: $node->getTitle(),
        'path'   => $node->get('field_nav_path')->value,
        'is_cta' => (bool) $node->get('field_nav_is_cta')->value,
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

  public function processSteps(): JsonResponse {
    $page_nodes = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type'       => 'basic_page',
      'field_slug' => 'process',
      'status'     => 1,
    ]);

    if (empty($page_nodes)) {
      return new JsonResponse([], 200, $this->cors());
    }

    $page  = reset($page_nodes);
    $items = [];

    foreach ($page->get('field_process_steps') as $item) {
      $paragraph = $item->entity;
      if (!$paragraph) {
        continue;
      }

      $items[] = [
        'id'          => $paragraph->id(),
        'title'       => $paragraph->get('field_title')->value,
        'description' => $paragraph->get('field_step_description')->value,
      ];
    }

    return new JsonResponse($items, 200, $this->cors());
  }

  public function services(): JsonResponse {
    $page_nodes = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type'   => 'booking_page',
      'status' => 1,
    ]);

    if (empty($page_nodes)) {
      return new JsonResponse([], 200, $this->cors());
    }

    $page  = reset($page_nodes);
    $items = [];

    foreach ($page->get('field_services') as $item) {
      $paragraph = $item->entity;
      if (!$paragraph) {
        continue;
      }

      $items[] = [
        'id'    => $paragraph->id(),
        'title' => $paragraph->get('field_service_title')->value,
      ];
    }

    return new JsonResponse($items, 200, $this->cors());
  }

  public function faqPage(): array {
    $page_nodes = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type'   => 'faq_page',
      'status' => 1,
    ]);

    if (empty($page_nodes)) {
      return ['#markup' => 'No FAQ content found.'];
    }

    $page = reset($page_nodes);
    $rows = [];

    foreach ($page->get('field_faq_items') as $item) {
      $paragraph = $item->entity;
      if (!$paragraph) {
        continue;
      }
      $rows[] = [
        $paragraph->get('field_question')->value,
        ['data' => ['#markup' => $paragraph->get('field_answer')->value]],
      ];
    }

    return [
      'heading' => [
        '#type'  => 'html_tag',
        '#tag'   => 'h1',
        '#value' => $page->getTitle(),
      ],
      'table' => [
        '#type'   => 'table',
        '#header' => ['Question', 'Answer'],
        '#rows'   => $rows,
        '#empty'  => 'No FAQ items.',
      ],
    ];
  }

  public function pricelistPage(): array {
    $page_nodes = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type'   => 'pricelist_page',
      'status' => 1,
    ]);

    if (empty($page_nodes)) {
      return ['#markup' => 'No price list content found.'];
    }

    $page   = reset($page_nodes);
    $grades = [];
    $rows   = [];

    foreach ($page->get('field_price_items') as $item) {
      $paragraph = $item->entity;
      if (!$paragraph) {
        continue;
      }

      $service = $paragraph->get('field_service')->value;
      $prices  = [];

      foreach ($paragraph->get('field_price_entries') as $entry_item) {
        $entry = $entry_item->entity;
        if (!$entry) {
          continue;
        }
        $grade = $entry->get('field_grade')->value;
        $price = (int) $entry->get('field_price')->value;
        if (!in_array($grade, $grades)) {
          $grades[] = $grade;
        }
        $prices[$grade] = $price;
      }

      $row = [$service];
      foreach ($grades as $grade) {
        $row[] = isset($prices[$grade]) ? 'kr. ' . number_format($prices[$grade], 0, ',', '.') : '—';
      }
      $rows[] = $row;
    }

    $header = array_merge(['Service'], $grades);

    return [
      'heading' => [
        '#type'  => 'html_tag',
        '#tag'   => 'h1',
        '#value' => $page->getTitle(),
      ],
      'table' => [
        '#type'   => 'table',
        '#header' => $header,
        '#rows'   => $rows,
        '#empty'  => 'No price items.',
      ],
    ];
  }

  public function processPage(): array {
    $page_nodes = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type'       => 'basic_page',
      'field_slug' => 'process',
      'status'     => 1,
    ]);

    if (empty($page_nodes)) {
      return ['#markup' => 'No process content found.'];
    }

    $page  = reset($page_nodes);
    $items = [];

    foreach ($page->get('field_process_steps') as $item) {
      $paragraph = $item->entity;
      if (!$paragraph) {
        continue;
      }
      $items[] = $paragraph->get('field_title')->value . ': ' . $paragraph->get('field_step_description')->value;
    }

    return [
      'heading' => [
        '#type'  => 'html_tag',
        '#tag'   => 'h1',
        '#value' => $page->getTitle(),
      ],
      'steps' => [
        '#theme' => 'item_list',
        '#items' => $items,
        '#list_type' => 'ol',
      ],
    ];
  }

  public function layout(): JsonResponse {
    return new JsonResponse([
      'nav'      => json_decode($this->nav()->getContent(), TRUE),
      'settings' => json_decode($this->settings()->getContent(), TRUE),
    ], 200, $this->cors());
  }

  public function pageDataFaq(): JsonResponse {
    $pages = json_decode($this->pages()->getContent(), TRUE);
    $faq   = json_decode($this->faq()->getContent(), TRUE);
    return new JsonResponse(['page' => $pages, 'faq' => $faq], 200, $this->cors());
  }

  public function pageDataPricelist(): JsonResponse {
    $pages     = json_decode($this->pages()->getContent(), TRUE);
    $pricelist = json_decode($this->pricelist()->getContent(), TRUE);
    return new JsonResponse(['page' => $pages, 'pricelist' => $pricelist], 200, $this->cors());
  }

  public function pageDataProcess(): JsonResponse {
    $pages = json_decode($this->pages()->getContent(), TRUE);
    $steps = json_decode($this->processSteps()->getContent(), TRUE);
    return new JsonResponse(['page' => $pages, 'steps' => $steps], 200, $this->cors());
  }

  public function pageDataBooking(): JsonResponse {
    $pages    = json_decode($this->pages()->getContent(), TRUE);
    $services = json_decode($this->services()->getContent(), TRUE);
    return new JsonResponse(['page' => $pages, 'services' => $services], 200, $this->cors());
  }

  private function getNavCards($node): array {
    if (!$node->hasField('field_home_nav_cards') || $node->get('field_home_nav_cards')->isEmpty()) {
      return [];
    }
    $cards = [];
    foreach ($node->get('field_home_nav_cards') as $item) {
      $paragraph = $item->entity;
      if (!$paragraph) continue;
      $imageUrl = '';
      $imageField = $paragraph->get('field_card_image');
      if (!$imageField->isEmpty()) {
        $file = $imageField->first()->get('entity')->getTarget()?->getValue();
        if ($file) {
          $imageUrl = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
        }
      }
      $path  = $paragraph->get('field_card_path')->value ?? '';
      $label = '';

      if (!empty($path)) {
        $system_path = \Drupal::service('path_alias.manager')->getPathByAlias($path);
        if (preg_match('/\/node\/(\d+)/', $system_path, $matches)) {
          $linked_node = $this->entityTypeManager->getStorage('node')->load($matches[1]);
          if ($linked_node) {
            $label = $linked_node->getTitle();
          }
        }
      }

      $cards[] = [
        'image' => $imageUrl,
        'label' => $label,
        'path'  => $path,
      ];
    }
    return $cards;
  }

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
