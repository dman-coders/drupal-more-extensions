<?php

namespace Drupal\DrupalMoreExtensions\Context;

use Behat\MinkExtension\Context\RawMinkContext;

/**
 * Defines application features from the specific context.
 */
class ScreenshotContext extends RawMinkContext {

  /**
   * Base path for saving screenshots into.
   *
   * @var string
   */
  protected $path;

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
   * @param $params
   */
  public function __construct($params = array()) {

    $params += array(
      'path' => 'screenshots',
    );

    $this->path = rtrim($params['path'], '/') . '/';
    $this->started = new \DateTime();
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
    $dimensions = array(
      'wide' => array(
        'width' => 1600,
        'height' => 1080,
      ),
      'medium' => array(
        'width' => 1024,
        'height' => 800,
      ),
      'narrow' => array(
        'width' => 740,
        'height' => 600,
      ),
      'mobile' => array(
        'width' => 320,
        'height' => 480,
      ),
    );
    if (isset($dimensions[$size])) {
      $this->getSession()->getDriver()->resizeWindow($dimensions[$size]['width'], $dimensions[$size]['height']);
    }
    else {
      throw new Exception('Unknown named screensize');
    }
  }


  /**
   * Take a snapshot of the browser.
   *
   * This works for phantomjs, Selenium and other real browsers
   * that support screenshots.
   *
   * http://stackoverflow.com/questions/22630350/how-can-i-write-a-behat-step-that-will-capture-a-screenshot-or-html-page
   *
   * @Then /^show me a screenshot$/
   */
  public function show_me_a_screenshot() {
    $image_data = $this->getSession()->getDriver()->getScreenshot();
    $filepath = '/tmp/behat_screenshot.jpg';
    file_put_contents($filepath, $image_data);
    $this->open_image_file($filepath);
  }

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
  public function show_me_the_html_page_in_the_browser() {

    $html_data = $this->getSession()->getDriver()->getContent();
    $filepath = '/tmp/behat_page.html';
    file_put_contents($filepath, $html_data);

    if (PHP_OS === "Darwin" && PHP_SAPI === "cli") {
      exec('open -a "Safari.app" ' . $filepath);
    };
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
   *
   * https://gist.github.com/amenk/11208415
   */
  public function takeAScreenshotOfAndSave($selector, $filename) {
    $this->saveScreenshot($filename, '/tmp');
    // This requires jquery to be available on the page already.
    $js = 'return jQuery("' . $selector . '")[0].getBoundingClientRect();';
    $pos = $this->getSession()->evaluateScript($js);
    $dst_image = imagecreatetruecolor(round($pos['width']), round($pos['height']));
    $src_image = imagecreatefrompng("/tmp/" . $filename);
    imagecopyresampled(
      $dst_image, $src_image,
      0, 0,
      round($pos['left']), round($pos['top']), round($pos['width']), round($pos['height']),
      round($pos['width']), round($pos['height']));

    $screenshotPath = $this->getScreenshotPath();
    $screenshotFile = $screenshotPath . $this->getFilepath($filename);
    $this->ensureDirectoryExists($screenshotFile);

    imagepng($dst_image, $screenshotFile);

    echo "Saved element screenshot as $screenshotFile";
    $this->open_image_file($screenshotFile);

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
    return $this->path . $this->started->format('YmdHis') . '/';
  }

  /**
   * Returns the relative file path for the given step
   *
   * @param StepNode $step
   * @return string
   */
  protected function getFilepath($filename) {
    // It would be better if I created a folder structure named after the
    // feature being tested. But I can't TELL which task or step is
    // currently running. It's difficult to get that from the context.
    return $this->formatString($filename) . '.png';

    return sprintf('%s/%s/%s.png',
      $this->formatString($this->currentScenario->getFeature()->getTitle()),
      $this->formatString($this->currentScenario->getTitle()),
      $filename
    );
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
      return mkdir($dir, 0777, true);
    }
    return true;
  }

}
