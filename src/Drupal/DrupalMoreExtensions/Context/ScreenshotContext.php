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
    $params += array(
      'path' => 'screenshots',
      'timestamped' => TRUE,
    );

    $this->path = rtrim($params['path'], '/') . '/';
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
    $this->open_image_file($filepath);
  }

  /**
   * Helper function for diagnostics. Throw the file into a desktop viewer.
   *
   * This will need to be different per OS.
   *
   * @param $filepath
   */
  public function open_image_file($filepath) {
    if (PHP_OS === "Darwin" && PHP_SAPI === "cli") {
      exec('open -a "Preview.app" ' . $filepath);
    }
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
   * https://gist.github.com/amenk/11208415
   */
  public function takeAScreenshotOfAndSave($selector, $filename) {

    // Element must be visible on screen - scroll if needed.
    // Scroll to align bottom by default (if needed) as align top usually
    // doesn't tell the right story.
    $javascript = 'return jQuery("' . $selector . '")[0].scrollIntoView(false);';
    $this->getSession()->evaluateScript($javascript);

    // Snapshot the visible screen.
    $screen_filepath = '/tmp/' . $filename;
    file_put_contents($screen_filepath, $this->getSession()->getScreenshot());

    // Get element dimensions for cropping.
    // This calculation requires jquery to be available on the page already.
    $javascript = 'return jQuery("' . $selector . '")[0].getBoundingClientRect();';
    $pos = $this->getSession()->evaluateScript($javascript);

    $dst_filepath = $this->getScreenshotPath() . $this->getFilepath($filename);
    $this->crop_and_save($screen_filepath, $pos, $dst_filepath);
    echo "Saved element screenshot as $dst_filepath";
    $this->open_image_file($dst_filepath);

    // Optionally
    // Create a context thumbnail.
    $context_filepath =  $this->getScreenshotPath() . $this->getFilepath($filename . '-context');
    $this->generateContextualizedScreenshotOfElement($screen_filepath, $dst_filepath, $context_filepath, $pos);
    $this->open_image_file($context_filepath);
  }

  /**
   * Create a context thumbnail of the selected element inside the whole page.
   *
   * Helper function for takeAScreenshotOfAndSave().
   *
   * @param string $screen_filepath
   *   Full page image file path.
   * @param string $element_filepath
   *   Already generated element image file path.
   * @param string $context_filepath
   *   Destination path to save the highlighted compostie into.
   * @param array $pos
   *   Location and dimensions of the element.
   */
  private function generateContextualizedScreenshotOfElement($screen_filepath, $element_filepath, $context_filepath, $pos) {
    $src_image = imagecreatefrompng($screen_filepath);
    $component_img = imagecreatefrompng($element_filepath);

    // Blur the full screenshot.
    imagefilter($src_image, IMG_FILTER_SELECTIVE_BLUR);
    imagefilter($src_image, IMG_FILTER_BRIGHTNESS, 80);
    imagefilter($src_image, IMG_FILTER_BRIGHTNESS, -20);

    // Outline the region.
    $border_width = 10;
    $border_color = imagecolorallocate($src_image, 255, 64, 64);
    imagefilledrectangle($src_image , $pos['left'] - $border_width, $pos['top'] - $border_width, $pos['left'] + $pos['width'] + $border_width, $pos['top'] + $pos['height'] + $border_width, $border_color);

    // Paste the (unblurred) cropped image back over top where it was.
    imagecopy($src_image, $component_img, $pos['left'], $pos['top'], 0, 0, $pos['width'], $pos['height']);

    // Shrink it all
    $percent = 0.25;
    $width = imagesx($src_image);
    $height = imagesy($src_image);
    $newwidth = $width * $percent;
    $newheight = $height * $percent;
    $thumb_image = imagecreatetruecolor($newwidth, $newheight);
    imagecopyresampled($thumb_image, $src_image, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
    imagepng($thumb_image, $context_filepath);
  }

  /**
   * Helper routine to take a slice out of the bigger iamge.
   *
   * @param $src_filepath
   * @param $pos
   * @param $dst_filepath
   */
  function crop_and_save($src_filepath, $pos, $dst_filepath) {
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
    return $this->formatString($filename) . '.png';
  }

  /**
   * Formats a title string into a filename friendly string
   *
   * @param string $string
   * @return string
   */
  protected function formatString($string) {
    $string = preg_replace('/[^\w\s\-]/', '', $string);
    $string = preg_replace('/[\s\-]+/', '-', $string);
    return $string;
  }

  /**
   * Ensure the directory where the file will be saved exists.
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
