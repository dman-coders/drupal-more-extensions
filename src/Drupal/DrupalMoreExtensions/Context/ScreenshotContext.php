<?php

namespace Drupal\DrupalMoreExtensions\Context;

use Behat\Mink\Exception\ElementNotFoundException;
use Behat\MinkExtension\Context\RawMinkContext;

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
   *
   * @var string
   */
  protected $screenFilepath;

  /**
   * Internal memo. Where additional local files may be found.
   *
   * Usually this contextfile directory, but may be overridden via configs.
   *
   * @var string
   */
  protected $resourceDirpath;

  /**
   * Internal memo. Array of dimensions used for cropping.
   *
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
   * @param array $params
   *   Setup configs. Expected to contain:
   *   'path' string for screenshots.
   *   'timestamped' bool for whether to include a time in the filepath.
   */
  public function __construct($params = array()) {

    // Resource files are expected to be found near this code.
    $this->resourceDirpath = dirname(__FILE__) . '/';
    $this->xslFilepath = $this->resourceDirpath . 'styleguide_presentation.xsl';

    // Set default params - these may also be set from above.
    $params += array(
      'path' => 'screenshots',
      'timestamped' => TRUE,
    );
    $this->path = rtrim($params['path'], '/') . '/';
    $this->ensureDirectoryExists($this->path);
    $this->timestamped = (bool) $params['timestamped'];
    $this->started = new \DateTime();
    // TODO - the start time is currently only per-scanario, to per test run.
    // so it's not doing its job as a collective run-task grouper.
  }

  /**
   * Set the browser width.
   *
   * Expected parameters: wide|medium|narrow|mobile.
   * Passing in a number pattern like 640x480 will also be accepted.
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
    if (preg_match('/^\d+x\d+$/', $size)) {
      // Given an XxY string. That'll work too.
      list($width, $height) = explode('x', $size);
      $dimensions[$size] = array('width' => $width, 'height' => $height);
    }
    if (isset($dimensions[$size])) {
      $this->getSession()
        ->getDriver()
        ->resizeWindow($dimensions[$size]['width'], $dimensions[$size]['height']);
    }
    else {
      throw new \InvalidArgumentException("Unknown named screensize. No preset named '$size' is defined.");
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
  public function showScreenshot() {
    $image_data = $this->getSession()->getDriver()->getScreenshot();
    $filepath = '/tmp/behat_screenshot.jpg';
    file_put_contents($filepath, $image_data);
    $this->openImageFile($filepath);
  }

  /**
   * This works for the Goutte driver and I assume other HTML-only ones.
   *
   * Http://stackoverflow.com/questions/22630350/how-can-i-write-a-behat-step-that-will-capture-a-screenshot-or-html-page.
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
   * Take a screenshot of the full current window.
   *
   * @Then I take a screenshot and save as :arg1
   */
  public function takeScreenshotAndSaveAs($filename) {
    // Snapshot the visible screen.
    $dst_filepath = $this->getScreenshotPath() . $this->getFilepath($filename);
    $this->ensureDirectoryExists($dst_filepath);
    file_put_contents($dst_filepath, $this->getSession()->getScreenshot());
  }

  /*
   * Functionality copied from Zodyac PerceptualDiffExtension.
   *
   * Instead of a behavior, make taking screenshots and checking
   * diffs steps an *action* .
   * Retain most of the conventions for file management as
   * PerceptualDiffExtension does however. To do that, we have
   * borrowed a handful of its util functions directly.
   */

  /**
   * Locate a named element in the current page and snapshot just that.
   *
   * Https://gist.github.com/amenk/11208415
   *
   * @param string $selector
   *   The selector defined here is expected to be a jquery selector.
   * @param string $filename
   *   Indicative filename to save as. THis will be sanitized and placed in a
   *   subfolder according to active configs.
   *
   * @return string
   *   Result filepath.
   *
   * @Then /^take a screenshot of "([^"]*)" and save "([^"]*)"$/
   * @Then /^take a screenshot of "([^"]*)" and save as "([^"]*)"$/
   * @Then I take a screenshot of :arg1 and save :arg2
   */
  public function takeScreenshotOfAndSaveAs($selector, $filename) {

    // Element must be visible on screen - scroll if needed.
    // First assert the element selector can be found.
    // I attempted to use the same logic that MinkContext elementExists() does,
    // but failed. Instead:
    $javascript = 'return jQuery("' . $selector . '")[0];';
    $element = $this->getSession()->evaluateScript($javascript);
    if (NULL === $element) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'element', 'id|name|label|value|placeholder', $selector);
    }

    // Scroll to align bottom by default (if needed) as align top usually
    // doesn't tell the right story.
    // Note, scroll has no effect in phantomjs, as the whole window is pictured.
    // OTOH, if the browser window doesn't show the whole thing, you'll get
    // black spots.
    $javascript = 'return jQuery("' . $selector . '")[0].scrollIntoView(false);';
    $this->getSession()->evaluateScript($javascript);

    // Snapshot the visible screen.
    $this->screenFilepath = '/tmp/' . $filename;
    file_put_contents($this->screenFilepath, $this->getSession()->getScreenshot());

    // Get element dimensions for cropping.
    // This calculation requires jquery to be available on the page already.
    $javascript = 'return jQuery("' . $selector . '")[0].getBoundingClientRect();';
    $this->pos = $this->getSession()->evaluateScript($javascript);

    $dst_filepath = $this->getScreenshotPath() . $this->getFilepath($filename);
    $this->cropAndSave($this->screenFilepath, $this->pos, $dst_filepath);
    echo "Saved element screenshot as $dst_filepath";
    $this->openImageFile($dst_filepath);

    return $dst_filepath;
  }

  /**
   * Utility helpers below here...
   *
   * These are not actions that should be invoked from outside.
   */

  /**
   * Helper routine to take a slice out of the bigger iamge.
   *
   * @param string $src_filepath
   *   Input filepath. Fully-justified.
   * @param array $pos
   *   An array of dimensions for cropping - left, top, width, height.
   * @param string $dst_filepath
   *   Output filepath.
   */
  protected function cropAndSave($src_filepath, $pos, $dst_filepath) {
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
   *   Filepath to the result.
   */
  protected function getScreenshotPath() {
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
   * @param string $filepath
   *   Filepath to the target file.
   */
  protected function openImageFile($filepath) {
    if (PHP_OS === "Darwin" && PHP_SAPI === "cli") {
      exec('open -a "Preview.app" ' . $filepath);
    }
  }

  /**
   * Returns the relative file path for the given step.
   *
   * @param string $filename
   *   Rough file description.
   *
   * @return string
   *   Sanitized string valid for use as a safe filename.
   */
  protected function getFilepath($filename) {
    // It would be better if I created a folder structure named after the
    // feature being tested. But I can't TELL which task or step is
    // currently running. It's difficult to get that from the context.
    return $this->sanitizeString($filename) . '.png';
  }

  /**
   * Formats a title string into a filename friendly string.
   *
   * @param string $string
   *   String to sanitize.
   *
   * @return string
   *   Filesystem-safe version of the string.
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
   *   Filepath to check.
   *
   * @return bool
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
