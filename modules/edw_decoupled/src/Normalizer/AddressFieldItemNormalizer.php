<?php

namespace Drupal\edw_decoupled\Normalizer;

use Drupal\address\Plugin\Field\FieldType\AddressItem;
use Drupal\address\Repository\CountryRepository;
use Drupal\serialization\Normalizer\FieldItemNormalizer;

/**
 * Adds the country label to address field value.
 */
class AddressFieldItemNormalizer extends FieldItemNormalizer {

  /**
   * The country repository service.
   *
   * @var \Drupal\address\Repository\CountryRepository
   */
  protected $countryRepository;

  /**
   * Constructs an AddressFieldItemNormalizer object.
   *
   * @param \Drupal\address\Repository\CountryRepository $countryRepository
   *   The country repository.
   */
  public function __construct(CountryRepository $countryRepository) {
    $this->countryRepository = $countryRepository;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($field_item, $format = NULL, array $context = []): array|string|int|float|bool|\ArrayObject|NULL {
    /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $field_item */
    $values = parent::normalize($field_item, $format, $context);
    if (empty($values['country_code'])) {
      return $values;
    }
    $country = $this->countryRepository->get($values['country_code']);
    $values['country_label'] = $country->getName();
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedTypes(?string $format): array {
    return [
      AddressItem::class => TRUE,
    ];
  }

}
