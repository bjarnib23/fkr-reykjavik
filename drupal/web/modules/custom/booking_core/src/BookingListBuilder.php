<?php

namespace Drupal\booking_core;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Utility\TableSort;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines the list builder for Booking entities.
 */
class BookingListBuilder extends EntityListBuilder {

  /**
   * Constructs a BookingListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    private readonly RequestStack $requestStack,
    private readonly DateFormatterInterface $dateFormatter,
  ) {
    parent::__construct($entity_type, $storage);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    return new self(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('request_stack'),
      $container->get('date.formatter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['name'] = [
      'data'      => $this->t('Name'),
      'specifier' => 'name',
      'field'     => 'name',
    ];
    $header['email'] = [
      'data'      => $this->t('Email'),
      'specifier' => 'email',
      'field'     => 'email',
    ];
    $header['service'] = [
      'data'      => $this->t('Service'),
      'specifier' => 'service',
      'field'     => 'service',
    ];
    $header['date'] = [
      'data'      => $this->t('Date'),
      'specifier' => 'date',
      'field'     => 'date',
      'sort'      => 'asc',
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity): array {
    $operations = [];

    if ($entity->hasLinkTemplate('canonical')) {
      $operations['view'] = [
        'title'  => $this->t('View'),
        'weight' => 0,
        'url'    => $entity->toUrl('canonical'),
      ];
    }

    if ($entity->access('delete') && $entity->hasLinkTemplate('delete-form')) {
      $operations['delete'] = [
        'title'  => $this->t('Delete'),
        'weight' => 100,
        'url'    => $this->ensureDestination($entity->toUrl('delete-form')),
      ];
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\booking_core\BookingInterface $entity */
    $row['name']    = $entity->getName();
    $row['email']   = $entity->getEmail();
    $row['service'] = $entity->getService() ?: '—';
    $row['date']    = $entity->getDate() ? $this->formatDate($entity->getDate()) : '—';
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds(): array {
    $allowed = ['name', 'email', 'service', 'date'];
    $header  = $this->buildHeader();
    $request = $this->requestStack->getCurrentRequest();

    $order     = TableSort::getOrder($header, $request);
    $sort      = TableSort::getSort($header, $request);
    $field     = in_array($order['sql'] ?? '', $allowed, TRUE) ? $order['sql'] : 'date';
    $direction = strtolower($sort) === 'desc' ? 'DESC' : 'ASC';

    return $this->getStorage()->getQuery()
      ->sort($field, $direction)
      ->accessCheck(FALSE)
      ->execute();
  }

  /**
   * Formats a UTC datetime string for display in the site timezone.
   */
  private function formatDate(string $date): string {
    $ts = (new \DateTime($date, new \DateTimeZone('UTC')))->getTimestamp();
    return $this->dateFormatter->format($ts, 'medium');
  }

}
