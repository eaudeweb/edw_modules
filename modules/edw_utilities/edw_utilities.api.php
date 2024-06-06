<?php

/**
 * @file
 * Hooks and documentation related to the EDW Utilities.
 */

use Drupal\taxonomy\Entity\Term;
use Drupal\views\Plugin\views\query\QueryPluginBase;

/**
 * Alter the query before it is executed.
 *
 * @param string $vid
 *   The vocabulary id.
 * @param string $input
 *   The text to extract named entities for.
 * @param \Drupal\Core\Entity\Query\Sql\Query $query
 *   The query plugin object for the query.
 * @param array $properties
 *   A list of additional properties.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function hook_terms_autocomplete_query_alter(string $vid, string $input, Query &$query, array $properties = []) {
  // For example, assuming the result should be sorted, grouped and limited to
  // 10 terms.
  $query
    ->groupBy('tid')
    ->sort('changed', 'DESC')
    ->range(0, 10);
}

/**
 * Alter the label before it is executed.
 *
 * @param string $vid
 *    The vocabulary id.
 * @param \Drupal\taxonomy\Entity\Term $term
 *    Term object.
 * @param string $label
 *   Label of the term.
 * @param array $properties
 *   A list of additional properties.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function hook_terms_autocomplete_label_alter(string $vid, Term $term, string &$label, array $properties = []) {
  // For example, filter by term instead of open the term page.
  if ($vid == 'climate_change_toolkit') {
    $request = \Drupal::requestStack()->getCurrentRequest();
    $referrer = $request->headers->get('referer');
    $query = [];
    if (!empty($referrer)) {
      $query = UrlHelper::parse($referrer)['query'];
    }
    $query['f'][] = 'keywords:' . $term->id();
    $url = Url::fromUserInput('/knowledge/toolkits/climate/legislation-explorer', [
      'query' => $query,
    ]);
    $label = sprintf('<a href="%s">%s</a>',
      $url->toString(),
      $term->label(),
    );
  }
}