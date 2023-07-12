<?php

namespace Drupal\mainspring_responsive_image_styles;

use Drupal\Component\Discovery\DiscoveryException;
use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Responsive image styles.
 */
class TwigMainspringExtension extends AbstractExtension {

  use StringTranslationTrait;

  /**
   * Root file path.
   *
   * @var string
   */
  protected $root;

  /**
   * The theme handler.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs the TwigMainspringExtension object.
   *
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(ThemeHandlerInterface $theme_handler, MessengerInterface $messenger, AccountInterface $current_user) {
    $this->themeHandler = $theme_handler;
    $this->messenger = $messenger;
    $this->currentUser = $current_user;
    $this->root = \Drupal::root();
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions(): array {
    $functions = [
      new TwigFunction('mainspring_responsive_image_style', [$this, 'responsiveImageStyle']),
    ];

    return $functions;
  }

  /**
   * Determines responsive image style.
   *
   * @return string
   *   The name of the responsive image style.
   */
  public function responsiveImageStyle($layout, $section_layout, $image_style, $zone = NULL) {

    $theme = $this->themeHandler->getDefault();
    $theme_info = $this->themeHandler->listInfo();
    $default_theme_info = $theme_info[$theme];
    $path = $default_theme_info->getPath();

    $file = $path . '/' . $theme . '.responsive_image_layouts.yml';
    $data = [];
    if (file_exists($this->root . '/' . $file)) {
      // If a file is empty or its contents are commented out, return an empty
      // array instead of NULL for type consistency.
      try {
        $data = Yaml::decode(file_get_contents($file)) ?: [];
      }
      catch (InvalidDataTypeException $e) {
        throw new DiscoveryException("The $file contains invalid YAML", 0, $e);
      }
    }

    $responsive_image_style = '';

    if (isset($data[$layout][$section_layout][$image_style])) {
      $option = $data[$layout][$section_layout][$image_style];
      if (is_array($option)) {
        if (!empty($zone)) {
          $n = trim($zone, 'zone');
          $n = $n - 1;
          if (isset($option[$n])) {
            $responsive_image_style = $option[$n];
          }
        }
        else {
          $responsive_image_style = reset($option);
        }
      }
      else {
        $responsive_image_style = $option;
      }
    }

    if (empty($responsive_image_style) && $this->currentUser->id() == 1) {
    // throw new \Exception("The reponsive image style could not be matched. Please check that the $file has a matching responsive image style set for your variables $layout $section_layout $image_style");
      $this->messenger->addError($this->t("The reponsive image style could not be matched. Please check that the @file has a matching responsive image style set for your variables @layout @section_layout @image_style",
        [
          '@file' => $file,
          '@layout' => $layout,
          '@section_layout' => $section_layout,
          '@image_style' => $image_style,
        ]
      ));
    }

    return $responsive_image_style;
  }

}
