<?php

namespace Drupal\DrupalMoreExtensions\Context;

use Behat\MinkExtension\Context\RawMinkContext;

/**
 * Defines application features from the specific context.
 */
class ScreenshotContext extends RawMinkContext {

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
    $file_and_path = '/tmp/behat_screenshot.jpg';
    file_put_contents($file_and_path, $image_data);

    if (PHP_OS === "Darwin" && PHP_SAPI === "cli") {
      exec('open -a "Preview.app" ' . $file_and_path);
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
    $file_and_path = '/tmp/behat_page.html';
    file_put_contents($file_and_path, $html_data);

    if (PHP_OS === "Darwin" && PHP_SAPI === "cli") {
      exec('open -a "Safari.app" ' . $file_and_path);
    };
  }

}
