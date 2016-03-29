<?php
/**
 * @file
 * Extends the screenshot-ability to generate a styleguide report.
 */

namespace Drupal\DrupalMoreExtensions\Context;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * Extends the screenshot-ability to generate a styleguide report.
 *
 * Produces an XML list and an HTML display of the named page elements.
 */
class StyleguideContext extends ScreenshotContext{

  /**
   * Location of the XSL used for beautifying the raw item list.
   *
   * @var string
   */
  protected $xslFilepath;

  /**
   * Where to link local resources like css and XSL and images relative to.
   *
   * This is used to construct URIs in the results. Ends with a /.
   *
   * @var string
   */
  protected $resourceDirpath;

  /**
   * Filepath to save the RSS-like XML analysis data into.
   *
   * @var string
   */
  protected $styleguideDataFilepath;

  /**
   * Filepath to save the HTML report into.
   *
   * @var string
   */
  protected $styleguideHtmlFilepath;


  /**
   * ScreenshotContext constructor.
   *
   * Set up params.
   *
   * @param string[] $params
   *   List of arbitrary parameters. @see parent.
   */
  public function __construct($params = array()) {
    // Run the parent constructor first, as that initializes the path.
    parent::__construct($params);

    // Additional params.
    // These get merged in after the path rules are defined,
    // as that may influence these.
    $defaults = array(
      'styleguideDataFilepath' => $this->path . 'styleguide.rss.xml',
      'styleguideHtmlFilepath' => $this->path . 'styleguide.html',
    );
    $params += $defaults;
    // The generated files get saved relative to the test run itself.
    // Unless otherwise set from above by params.
    $this->styleguideDataFilepath = $params['styleguideDataFilepath'];
    $this->styleguideHtmlFilepath = $params['styleguideHtmlFilepath'];
    // Paranoia protects us.
    foreach ($defaults as $param => $default) {
      if (empty($this->$param)) {
        throw new InvalidConfigurationException("Required data file path $param undefined. Expected something like '$default'");
      }
      if (is_dir($this->$param)) {
        throw new InvalidConfigurationException("Required data file path $param '{$this->$param}' is a dir not a file. Expected something like '$default'");
      }
    }
    $this->ensureDirectoryExists($this->styleguideDataFilepath);
    $this->ensureDirectoryExists($this->styleguideHtmlFilepath);
  }

  /**
   * @BeforeScenario
   *
   * This is the point where we can get a handle on the calling harness.
   *
   * - such as to note the name of the runnning test suite, which is invisible
   * to us usually. This is used to auto-generate filenames.
   *
   * http://behat.readthedocs.org/en/v3.0/guides/3.hooks.html#scenario-hooks
   */
  public function gatherContexts(\Behat\Behat\Hook\Scope\BeforeScenarioScope $scope) {
    /*
    print_r(array_keys((array)$scope));
    $environment = $scope->getEnvironment();
    print_r(array_keys((array)$environment));
    $feature = $scope->getFeature();
    $scenario = $scope->getScenario();
    print_r(array_keys((array)$feature));
    print_r(array_keys((array)$scenario));


    $featureTitle = $feature->getTitle();
    print_r($featureTitle);
    $featureFile = $feature->getFile();
    print_r($featureFile);

    $scenarioTitle = $scenario->getTitle();
    print_r($scenarioTitle);
    */
    // The docs pointed at this method to get a handle on neighbouring contexts
    //$minkContext = $environment->getContext('Behat\MinkExtension\Context\MinkContext');
    // WIP...
  }

  /**
   * Used to generate a larger report summary of snapshotted elements.
   *
   * @Then I take a screenshot of :arg1 and describe it as :arg2
   */
  public function takeScreenshotOfAndDescribeItAs($selector, $description) {
    // Generate auto-filename.
    $url = $this->getSession()->getCurrentUrl();
    $path = parse_url($url, PHP_URL_PATH);
    $filename = $this->sanitizeString($path . '--' . $selector);

    // Now do the screenshotting.
    $dst_filepath = $this->takeScreenshotOfAndSaveAs($selector, $filename);
    // Also to the contextual screenshot.
    $context_filepath = $this->getScreenshotPath() . $this->getFilepath($filename . '-context');
    $this->generateContextualizedScreenshotOfElement($dst_filepath, $context_filepath);

    // Append this new entry to the running list.
    $channel = $this->getStyleguideDOM($this->styleguideDataFilepath);
    $xml = $channel->ownerDocument;
    $media_ns = "http://search.yahoo.com/mrss/";

    // If an entry with this ID exists, replace it. Maintaining the order.
    // Can't use getElementByID safely without exceptions, so xpath.
    $xpath = new \DOMXPath($xml);
    $existing = $xpath->query("//item[guid = '" . $filename . "']")->item(0);

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
    $rel_path = $this->dissolveUrl($this->styleguideDataFilepath, $dst_filepath);
    $screenshot->setAttribute('url', $rel_path);
    $screenshot->setAttribute('type', 'image/png');
    $screenshot->setAttribute('isDefault', 'true');
    $context = $xml->createElementNS($media_ns, 'media:content');
    $item->appendChild($context);
    $rel_path = $this->dissolveUrl($this->styleguideDataFilepath, $context_filepath);
    $context->setAttribute('url', $rel_path);
    $context->setAttribute('type', 'image/png');

    // Save the result.
    file_put_contents($this->styleguideDataFilepath, $xml->saveXML());
    print("Updated $this->styleguideDataFilepath");

    $this->iRebuildTheStyleguide();

  }

  /**
   * Used to generate a larger report summary of snapshotted elements.
   *
   * Not sure if this is really the thing to call as an action, or as a helper.
   * Use the behat verbing anyway, for consistency.
   *
   * Note that this is a separate action from building the items, and uses a
   * running data doc - the XML that gets insterted into.
   * So you can process individual fragments, and rebuild this
   * report, without re-running every analysis each time.
   * Only the changes are changed.
   *
   * @Then I rebuild the style guide
   */
  public function iRebuildTheStyleguide() {
    // After updating the item list, regenerate the HTML also, to
    // avoid XSLT for folk that don't play that.
    $xslt = new \XsltProcessor();
    $xsl = new \DOMDocument;
    $xsl->load($this->xslFilepath);
    $xslt->importStylesheet($xsl);

    // Pass in the optional location to pull css from.
    // This relative reference calculation probably won't stand up
    // to portability, but may help enough during local testing.
    $resourceDirpath = $this->dissolveUrl(realpath($this->styleguideHtmlFilepath), realpath($this->resourceDirpath)) . '/';
    $xslt->setParameter('', 'resourceDirpath', $resourceDirpath);
    // Inlining would have been much easier ;-).
    // In fact, spitting out HTML and not doing XSL would too.

    $xml = new \DOMDocument;
    $xml->load($this->styleguideDataFilepath);

    $html = $xslt->transformToXML($xml);
    file_put_contents($this->styleguideHtmlFilepath, $html);
    print("Updated $this->styleguideHtmlFilepath");
  }

  /**
   * Utility helpers below here...
   */

  /**
   * Create a context thumbnail of the selected element inside the whole page.
   *
   * Helper function for takeAScreenshotOfAndSave().
   *
   * Private properties $screen_filepath, $pos, should be available.
   *
   * @param string $element_filepath
   *   Already generated element image file path.
   * @param string $context_filepath
   *   Destination path to save the highlighted compostie into.
   */
  private function generateContextualizedScreenshotOfElement($element_filepath, $context_filepath) {
    // Pull these values from the current objects memory to avoid passing them
    // around too much.
    $src_image = imagecreatefrompng($this->screenFilepath);
    $pos = $this->pos;

    $component_img = imagecreatefrompng($element_filepath);

    // Blur the full screenshot.
    imagefilter($src_image, IMG_FILTER_SELECTIVE_BLUR);
    imagefilter($src_image, IMG_FILTER_BRIGHTNESS, 80);
    imagefilter($src_image, IMG_FILTER_BRIGHTNESS, -20);

    // Outline the region.
    $border_width = 10;
    $border_color = imagecolorallocate($src_image, 255, 64, 64);
    imagefilledrectangle($src_image, $pos['left'] - $border_width, $pos['top'] - $border_width, $pos['left'] + $pos['width'] + $border_width, $pos['top'] + $pos['height'] + $border_width, $border_color);

    // Paste the (unblurred) cropped image back over top where it was.
    imagecopy($src_image, $component_img, $pos['left'], $pos['top'], 0, 0, $pos['width'], $pos['height']);

    // Shrink it all.
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
   * Either fetch and load, or initialize a new RSS-like data storage XML file.
   *
   * @param string $filepath
   *   Filepath of the source data.
   *
   * @return \DOMElement
   *   Dom to add data to (or pull from).
   */
  private function getStyleguideDOM($filepath) {
    $media_ns = "http://search.yahoo.com/mrss/";
    if (is_file($filepath)) {
      $xml = new \DOMDocument();
      print("loading $filepath");
      $xml->load($filepath);
      $channels = $xml->getElementsByTagName('channel');
      $channel = $channels->item(0);
    }
    else {
      $xml = new \DOMDocument( "1.0", "utf-8" );
      // Stylesheet to start with.
      $xslt = $xml->createProcessingInstruction('xml-stylesheet', 'type="text/xsl" href="' . $this->xslFilepath . '"');
      $xml->appendChild($xslt);
      // Add RSS and CHANNEL nodes.
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
   * If I were in $base, and trying to find my way to $url, resolve a path.
   *
   * Used to ensure the data xml (and the resulting html) links to the local
   * images no matter what user-defined storage paths were provided for either.
   *
   * Very incomplete- does not really do URLs yet, just paths.
   *
   * @param string $base
   *   Page path.
   * @param string $url
   *   Resource path, relative to the same place $base was.
   *
   * @return string
   *   Localised relative path, suitable for using to get from base to url.
   */
  protected function dissolveUrl($base, $url) {
    // eg
    // 'subfolder/base.htm'
    // 'subfolder/style.css'
    // should return
    // 'style.css'
    //
    // 'local/path/base.htm'
    // 'local/resources/style.css'
    // should return
    // '../resources/style.css'.
    $base_path_parts = explode('/', parse_url($base, PHP_URL_PATH));
    if (!empty(end($base_path_parts))) {
      array_pop($base_path_parts);
    }
    $url_path_parts = explode('/', parse_url($url, PHP_URL_PATH));
    $trimming = TRUE;
    $new_url = $url_path_parts;
    foreach ($base_path_parts as $i => $segment) {
      if ($trimming && $base_path_parts[$i] == $url_path_parts[$i]) {
        // Shorten them.
        array_shift($new_url);
      }
      else {
        array_unshift($new_url, '..');
      }
    }
    return implode('/', $new_url);
  }

}
