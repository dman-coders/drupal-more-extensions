<?php

namespace Drupal\DrupalMoreExtensions\Context;

use Behat\MinkExtension\Context\RawMinkContext;
use Behat\MinkExtension\Context\MinkAwareContext;

/**
 * Defines application features (actions) for working with screenshots.
 *
 * As it extends 'Mink' (to get the screenshot-ability) it is not natively
 * aware of 'Behat' so needs help to know about things like behat scenarios
 * or params.
 */
class ScreenshotContext extends RawMinkContext {

  /**
   * Base path for saving screenshots into.
   *
   * Ends with a '/'.
   *
   * @var string
   */
  protected $path;

  /**
   * Whether to include timestamps when constructing filepaths.
   *
   * This can be used either to compare multiple test runs (true)
   * Or if you just want to collect a number of screenshots (false).
   *
   * @var string
   */
  protected $timestamped;

  /**
   * When the suite was started.
   *
   * Used for constructiong timestamed directories for screenshots.
   *
   * @var \DateTime
   */
  protected $started;

  /**
   * Internal memo. The last screenshot taken in case of re-use.
   * @var string
   */
  protected $screen_filepath;

  /**
   * Internal memo. Array of dimensions used for cropping.
   * @var array
   */
  protected $pos;

  /**
   * ScreenshotContext constructor.
   *
   * Set up params - pathname and timestamp for saving the screenshot files.
   *
   * These are expected to be provided by the behat.yml file,
   * or optionally defined via commandline parameters.
   *
   *    default:
   *      suites:
   *        default:
   *          contexts:
   *            - Drupal\DrupalMoreExtensions\Context\ScreenshotContext:
   *                params:
   *                  path: 'screenshots'
   *                  timestamped: false
   *
   * TAKE CARE ON THE INDENTS in YAML here ^
   *
   * @param $params
   */
  public function __construct($params = array()) {

    // Resource files are expected to be found near this code.
    $this->resource_dirpath = dirname(__FILE__) . '/';
    $this->xsl_filepath = $this->resource_dirpath . 'styleguide_presentation.xsl';

    // Set default params - these may also be set from above.
    $params += array(
      'path' => 'screenshots',
      'timestamped' => TRUE,
    );
    $this->path = rtrim($params['path'], '/') . '/';
    $this->ensureDirectoryExists($this->path );
    $this->timestamped = (bool) $params['timestamped'];
    $this->started = new \DateTime();
    # TODO - the start time is currently only per-scanario, to per test run.
    # so it's not doing its job as a collective run-task grouper.
  }


  /**
   * Set the browser width.
   *
   * Expected parameters: wide|medium|narrow|mobile
   *
   * @Given the viewport is :arg1
   * @Given the viewport is wide/medium/narrow/mobile
   */
  public function theViewportIs($size) {
    // Viewports sometimes higher than natural to illustrate scrolled content.
    $dimensions = array(
      'wide' => array(
        'width' => 1600,
        'height' => 1080,
      ),
      'medium' => array(
        'width' => 1024,
        'height' => 768,
      ),
      'narrow' => array(
        'width' => 740,
        'height' => 1024,
      ),
      'mobile' => array(
        'width' => 320,
        'height' => 740,
      ),
    );
    if (isset($dimensions[$size])) {
      $this->getSession()
        ->getDriver()
        ->resizeWindow($dimensions[$size]['width'], $dimensions[$size]['height']);
    }
    else {
      // eep, need a namespace on exçeption!?
      throw new Exception('Unknown named screensize');
    }
  }


  /**
   * Take a snapshot of the browser.
   *
   * This version does NOT save the screenshot anywhere useful (just /tmp)
   * and throws the result at your screen.
   * Use for runtime diagnostics, NOT automated tests.
   *
   * This works for phantomjs, Selenium and other real browsers
   * that support screenshots.
   *
   * http://stackoverflow.com/questions/22630350/how-can-i-write-a-behat-step-that-will-capture-a-screenshot-or-html-page
   *
   * @Then /^show me a screenshot$/
   */
  public function showMeAScreenshot() {
    $image_data = $this->getSession()->getDriver()->getScreenshot();
    $filepath = '/tmp/behat_screenshot.jpg';
    file_put_contents($filepath, $image_data);
    $this->openImageFile($filepath);
  }

  /**
   * This works for the Goutte driver and I assume other HTML-only ones.
   *
   * http://stackoverflow.com/questions/22630350/how-can-i-write-a-behat-step-that-will-capture-a-screenshot-or-html-page
   *
   * @Then /^show me the HTML page$/
   */
  public function showMeTheHtmlPageInTheBrowser() {
    $html_data = $this->getSession()->getDriver()->getContent();
    $filepath = '/tmp/behat_page.html';
    file_put_contents($filepath, $html_data);

    if (PHP_OS === "Darwin" && PHP_SAPI === "cli") {
      exec('open -a "Safari.app" ' . $filepath);
    };
  }

  /**
   * @Then I take a screenshot and save as :arg1
   */
  public function iTakeAScreenshotAndSaveAs($filename) {
    // Snapshot the visible screen.
    $dst_filepath = $this->getScreenshotPath() . $this->getFilepath($filename);
    $this->ensureDirectoryExists($dst_filepath);
    file_put_contents($dst_filepath, $this->getSession()->getScreenshot());
  }

    /////////////////////////////////////////////////
  /**
   * Functionality copied from Zodyac PerceptualDiffExtension.
   *
   * Instead of a behavior, make taking screenshots and checking
   * diffs steps an *action* .
   * Retain most of the conventions for file management as
   * PerceptualDiffExtension does however. To do that, we have
   * borrowed a handful of its util functions directly.
   */

  /**
   * @Then /^take a screenshot of "([^"]*)" and save "([^"]*)"$/
   * @Then /^take a screenshot of "([^"]*)" and save as "([^"]*)"$/
   * @Then I take a screenshot of :arg1 and save :arg2
   *
   * The selector defined here is expected to be a jquery selector.
   *
   * https://gist.github.com/amenk/11208415
   */
  public function takeAScreenshotOfAndSave($selector, $filename) {

    // Element must be visible on screen - scroll if needed.
    // Scroll to align bottom by default (if needed) as align top usually
    // doesn't tell the right story.
    // Note, scroll has no effect in phantomjs, as the whole window is pictured.
    // OTOH, if the browser window doesn't show the whole thing, you'll get
    // black spots.
    $javascript = 'return jQuery("' . $selector . '")[0].scrollIntoView(false);';
    $this->getSession()->evaluateScript($javascript);

    // Snapshot the visible screen.
    $this->screen_filepath = '/tmp/' . $filename;
    file_put_contents($this->screen_filepath, $this->getSession()->getScreenshot());

    // Get element dimensions for cropping.
    // This calculation requires jquery to be available on the page already.
    $javascript = 'return jQuery("' . $selector . '")[0].getBoundingClientRect();';
    $this->pos = $this->getSession()->evaluateScript($javascript);

    $dst_filepath = $this->getScreenshotPath() . $this->getFilepath($filename);
    $this->cropAndSave($this->screen_filepath, $this->pos, $dst_filepath);
    echo "Saved element screenshot as $dst_filepath";
    $this->openImageFile($dst_filepath);

    return $dst_filepath;
  }

  /**
   * Utility helpers below here...
   */

  /**
   * Helper routine to take a slice out of the bigger iamge.
   *
   * @param $src_filepath
   * @param $pos
   * @param $dst_filepath
   */
  function cropAndSave($src_filepath, $pos, $dst_filepath) {
    $dst_image = imagecreatetruecolor(round($pos['width']), round($pos['height']));
    $src_image = imagecreatefrompng($src_filepath);
    imagecopyresampled(
      $dst_image, $src_image,
      0, 0,
      round($pos['left']), round($pos['top']), round($pos['width']), round($pos['height']),
      round($pos['width']), round($pos['height']));
    $this->ensureDirectoryExists($dst_filepath);
    imagepng($dst_image, $dst_filepath);
  }

  /**
   * Returns the screenshot path.
   *
   * If the timestamp option is set, screenshots will be saved in a timestamped
   * folder.
   *
   * @return string
   */
  public function getScreenshotPath() {
    if ($this->timestamped) {
      return $this->path . $this->started->format('YmdHis') . '/';
    }
    else {
      return $this->path;
    }
  }

  /**
   * Helper function for diagnostics. Throw the file into a desktop viewer.
   *
   * This will need to be different per OS.
   *
   * @param $filepath
   */
  public function openImageFile($filepath) {
    if (PHP_OS === "Darwin" && PHP_SAPI === "cli") {
      exec('open -a "Preview.app" ' . $filepath);
    }
  }


  /**
   * Returns the relative file path for the given step
   *
   * @param $filename
   *
   * @return string
   */
  protected function getFilepath($filename) {
    // It would be better if I created a folder structure named after the
    // feature being tested. But I can't TELL which task or step is
    // currently running. It's difficult to get that from the context.
    return $this->sanitizeString($filename) . '.png';
  }

  /**
   * Formats a title string into a filename friendly string
   *
   * @param string $string
   * @return string
   */
  protected function sanitizeString($string) {
    $string = preg_replace('/[^\w\s\-]/', '', $string);
    $string = preg_replace('/[\s\-]+/', '-', $string);
    $string = trim($string, '-');
    return $string;
  }

  /**
   * Ensure the directory where the file will be saved exists.
   *
   * Run this often, as timestamped saves will need it.
   *
   * @param string $file
   * @return boolean
   *   True if the directory exists and false if it could not be created.
   */
  protected function ensureDirectoryExists($file) {
    $dir = dirname($file);
    if (!is_dir($dir)) {
      return mkdir($dir, 0777, TRUE);
    }
    return TRUE;
  }

}
