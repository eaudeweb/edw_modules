<?php

namespace Drupal\Tests\edw_document\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\edw_document\Traits\DocumentTestTrait;

/**
 * Base class for the browser tests covering document downloads.
 *
 * Collects what it takes to get edw_document installed at all, which is more
 * than the module says it needs.
 */
abstract class DocumentDownloadTestBase extends BrowserTestBase {

  use DocumentTestTrait;

  /**
   * {@inheritdoc}
   *
   * The config/install directory ships a node action, a media action and an
   * ultimate_cron job, but edw_document.info.yml declares none of those three
   * modules — so installing the module on a site without them fails outright
   * with an UnmetDependenciesException.
   */
  protected static $modules = [
    'edw_document',
    'node',
    'media',
    'ultimate_cron',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

}
