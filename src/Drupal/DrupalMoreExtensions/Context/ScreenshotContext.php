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
   * Location of the XSL used for beautifying the raw item list.
   */
  protected $xsl_filepath;

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
   * Where to link local resources like css and XSL and images relative to.
   *
   * This is used to construct URIs in the results. Ends with a /.
   */
  protected $resource_dirpath;

  protected $styleguide_data_filepath;
  protected $styleguide_html_filepath;


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

    // Additional params.
    // These get merged in after the path rules are defined,
    // as that may influence these.
    $params += array(
      'styleguide_data_filepath' => $this->path . 'styleguide.rss.xml',
      'styleguide_html_filepath' => $this->path . 'styleguide.html',
    );
    // The generated files get saved relative to the test run itself.
    // Unless otherwise set from above by params.
    $this->styleguide_data_filepath = $params['styleguide_data_filepath'];
    $this->styleguide_html_filepath = $params['styleguide_html_filepath'];
    $this->ensureDirectoryExists($this->styleguide_data_filepath);
    $this->ensureDirectoryExists($this->styleguide_html_filepath);

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
   * @Then I take a screenshot of :arg1 and describe it as :arg2
   *
   * Used to generate a larger report summary of snapshotted elements.
   */
  public function iTakeAScreenshotOfAndDescribeItAs($selector, $description) {
    // Generate auto-filename.
    $url = $this->getSession()->getCurrentUrl();
    $path = parse_url($url, PHP_URL_PATH);
    $filename = $this->sanitizeString($path . '--' . $selector);

    // Now do the screenshotting
    $dst_filepath = $this->takeAScreenshotOfAndSave($selector, $filename);
    // Also to the contextual screenshot
    $context_filepath =  $this->getScreenshotPath() . $this->getFilepath($filename . '-context');
    $this->generateContextualizedScreenshotOfElement($dst_filepath, $context_filepath);

    // Append this new entry to the running list.
    $channel = $this->getStyleguideDOM($this->styleguide_data_filepath);
    $xml = $channel->ownerDocument;
    $media_ns = "http://search.yahoo.com/mrss/";

    // If an entry with this ID exists, replace it. Maintaining the order.
    // Can't use getElementByID safely without exceptions, so xpath.
    $xp = new \DOMXPath($xml);
    $existing = $xp->query("//item[guid = '" . $filename . "']")->item(0);

    if ($existing) {
      $item = $xml->createElement('item');
      $existing->parentNode->replaceChild($item, $existing);
      $item->setAttribute('id', $filename);
    }
    else {
      $item = $xml->createElement('item');
      $item->setAttribute('id', $filename);
      $channel->appendChild($item);
    }

    $item->appendChild($xml->createElement('title', $description));
    $item->appendChild($xml->createElement('description', $selector));
    $item->appendChild($xml->createElement('guid', $filename));


    $screenshot = $xml->createElementNS($media_ns, 'media:content');
    $item->appendChild($screenshot);
    $rel_path = $this->dissolveUrl($this->styleguide_data_filepath, $dst_filepath);
    $screenshot->setAttribute('url', $rel_path);
    $screenshot->setAttribute('type', 'image/png');
    $screenshot->setAttribute('isDefault', 'true');
    $context = $xml->createElementNS($media_ns, 'media:content');
    $item->appendChild($context);
    $rel_path = $this->dissolveUrl($this->styleguide_data_filepath, $context_filepath);
    $context->setAttribute('url', $rel_path);
    $context->setAttribute('type', 'image/png');

    // Save the result.
    file_put_contents($this->styleguide_data_filepath, $xml->saveXML());
    print("Updated $this->styleguide_data_filepath");

    $this->iRebuildTheStyleguide();

  }

  /**
   * @Then I rebuild the style guide
   *
   * Used to generate a larger report summary of snapshotted elements.
   * Not sure if this is really the thing to call as an action, or as a helper.
   * Use the behat verbing anyway, for consistency.
   */
  public function iRebuildTheStyleguide() {
    // After updating the item list, regenerate the HTML also, to
    // avoid XSLT for folk that don't play that.
    $xp = new \XsltProcessor();
    $xsl = new \DOMDocument;
    $xsl->load($this->xsl_filepath);
    $xp->importStylesheet($xsl);
    // Pass in the optional location to pull css from.
    $xp->setParameter('', 'resource_dirpath', $this->resource_dirpath);

    $xml = new \DOMDocument;
    $xml->load($this->styleguide_data_filepath);

    $html = $xp->transformToXML($xml);
    file_put_contents($this->styleguide_html_filepath, $html);
    print("Updated $this->styleguide_html_filepath");
  }
  /**
   * Utility helpers below here...
   */

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
  private function generateContextualizedScreenshotOfElement($element_filepath, $context_filepath) {
    // Pull these values from the current objects memory to avoid passing them
    // around too much.
    $src_image = imagecreatefrompng($this->screen_filepath);
    $pos = $this->pos;

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
   * Either fetch and load, or initialize a new RSS-like data storage XML file.
   *
   * @param $styleguide_data_filepath
   *
   * @return DOMDocument
   */
  private function getStyleguideDOM($filepath) {
    $media_ns = "http://search.yahoo.com/mrss/";
    if (file_exists($filepath)) {
      $xml = new \DOMDocument();
      $xml->load($filepath);
      $channels = $xml->getElementsByTagName('channel');
      $channel = $channels->item(0);
    }
    else {
      $xml = new \DOMDocument( "1.0", "utf-8" );
      // Stylesheet to start with.
      $xslt = $xml->createProcessingInstruction('xml-stylesheet', 'type="text/xsl" href="' . $this->xsl_filepath . '"');
      $xml->appendChild($xslt);
      // add RSS and CHANNEL nodes.
      $rss = $xml->createElement('rss');
      $xml->appendChild($rss);
      $rss->setAttribute('version', '2.0');
      $channel = $xml->createElement('channel');
      $rss->appendChild($channel);
      $rss->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:media', $media_ns);
      // I would like to add metadata about the running test context about now.
      $channel->appendChild($xml->createElement('generator', __CLASS__));
    }
    return $channel;
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
   * If I were in $base, and trying to find my way to $url, resolve a path.
   *
   * Used to ensure the data xml (and the resulting html) links to the local
   * images no matter what user-defined storage paths were provided for either.
   *
   * Very incomplete- does not really do URLs yet, just paths.
   *
   * @param $base
   * @param $url
   */
  protected function dissolveUrl($base, $url) {
    // eg
    // 'subfolder/base.htm
    // 'subfolder/style.css
    // should return
    // 'style.css


    // 'local/path/base.htm
    // 'local/resources/style.css
    // should return
    // '../resources/style.css
    $base_path_parts = explode('/', parse_url($base, PHP_URL_PATH));
    if (!empty(end($base_path_parts))) {
      array_pop($base_path_parts);
    }
    $url_path_parts = explode('/', parse_url($url, PHP_URL_PATH));
    $trimming = TRUE;
    $new_url = $url_path_parts;
    foreach ($base_path_parts as $i => $segment) {
      if ($trimming && $base_path_parts[$i] == $url_path_parts[$i]) {
        // shorten them.
        array_shift($new_url);
      }
      else {
        array_unshift($new_url, '..');
      }
    }
    return implode('/', $new_url);
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
