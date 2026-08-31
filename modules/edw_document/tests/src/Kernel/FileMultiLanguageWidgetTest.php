<?php

namespace Drupal\Tests\edw_document\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\edw_document\Traits\DocumentTestTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\NodeType;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests how the multi-language file widget works out which tab is open.
 *
 * The widget puts one file sub-form per site language behind a set of
 * horizontal tabs, and all of them submit together. On submit it has to know
 * which tab the editor was actually on, because that decides which
 * translation's items get written back — the wrong answer silently moves an
 * editor's files onto another language.
 *
 * It reads that from the raw POST rather than from the form state, because
 * form state does not carry files that were just removed from a tab. That
 * makes it sensitive to POST shapes that do not look the way it expects.
 *
 * @coversDefaultClass \Drupal\edw_document\Plugin\Field\FieldWidget\FileMultiLanguageWidget
 *
 * @group edw_document
 */
class FileMultiLanguageWidgetTest extends KernelTestBase {

  use DocumentTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'node',
    'language',
    'edw_document',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('node');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['language']);

    ConfigurableLanguage::createFromLangcode('fr')->save();

    NodeType::create(['type' => 'document', 'name' => 'Document'])->save();
    $this->createFilesField('node', 'document');
  }

  /**
   * Tests that the language of the open tab is read from the submission.
   *
   * @covers ::getCurrentTabLanguage
   */
  public function testTheOpenTabsLanguageIsRead(): void {
    $langcode = $this->currentTabLanguage([
      'field_files' => [
        'languages' => [
          'field_files__languages__active_tab' => 'edit-field-files-fr',
        ],
      ],
    ]);

    $this->assertSame('fr', $langcode);
  }

  /**
   * Tests that a request that is not a widget submission falls back.
   *
   * Building the form for the first time is a GET, with nothing to read.
   *
   * @covers ::getCurrentTabLanguage
   */
  public function testAnEmptySubmissionFallsBackToTheCurrentLanguage(): void {
    $expected = \Drupal::languageManager()->getCurrentLanguage()->getId();

    $this->assertSame($expected, $this->currentTabLanguage([]));
  }

  /**
   * Tests that a submission from another form falls back.
   *
   * The widget can be on a form that posts other fields — the node form's
   * "Preview" button, for instance — with nothing for this field at all.
   *
   * @covers ::getCurrentTabLanguage
   */
  public function testSubmissionWithoutThisFieldFallsBackToCurrentLanguage(): void {
    $expected = \Drupal::languageManager()->getCurrentLanguage()->getId();

    $this->assertSame($expected, $this->currentTabLanguage(['title' => [['value' => 'Report']]]));
  }

  /**
   * Tests that a submission with no tab state falls back rather than warning.
   *
   * The field is posted, but without the horizontal tabs' hidden active-tab
   * input — which is what happens when the widget's JavaScript has not run,
   * and is the shape behind the reported
   * "Trying to access array offset on value of type null" warning.
   *
   * @covers ::getCurrentTabLanguage
   */
  public function testSubmissionWithNoTabStateFallsBackToCurrentLanguage(): void {
    $expected = \Drupal::languageManager()->getCurrentLanguage()->getId();

    $langcode = $this->currentTabLanguage([
      'field_files' => [
        'data' => [['fids' => '']],
      ],
    ]);

    $this->assertSame($expected, $langcode);
  }

  /**
   * Tests that the tab id is read as a langcode, not as a css class.
   *
   * The tab ids are built by cleaning "edit_{field}_{langcode}" into a css
   * identifier, so the langcode is whatever follows the last hyphen — and
   * field names contain underscores that become hyphens too.
   *
   * @covers ::getCurrentTabLanguage
   */
  public function testTheLangcodeIsTakenFromTheEndOfTheTabId(): void {
    $langcode = $this->currentTabLanguage([
      'field_files' => [
        'languages' => [
          'field_files__languages__active_tab' => 'edit-field-files-en',
        ],
      ],
    ]);

    $this->assertSame('en', $langcode);
  }

  /**
   * Returns the language the widget believes the open tab is in.
   *
   * The widget snapshots the current request when it is constructed, so the
   * request has to be in place before the plugin is instantiated.
   *
   * @param array $postValues
   *   The POST parameters of the submission.
   *
   * @return string
   *   The langcode.
   */
  protected function currentTabLanguage(array $postValues): string {
    $stack = \Drupal::service('request_stack');
    $request = Request::create('/node/add/document', 'POST', $postValues);
    // The kernel puts a mock session on the request it booted with, and tears
    // it down afterwards; a pushed request without one breaks that teardown.
    $request->setSession($stack->getCurrentRequest()->getSession());
    $stack->push($request);

    $widget = \Drupal::service('plugin.manager.field.widget')->createInstance('file_multi_language', [
      'field_definition' => FieldConfig::loadByName('node', 'document', 'field_files'),
      'settings' => [],
      'third_party_settings' => [],
    ]);

    $method = new \ReflectionMethod($widget, 'getCurrentTabLanguage');
    $method->setAccessible(TRUE);

    return $method->invoke($widget);
  }

}
