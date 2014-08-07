<?php

/**
 * @file
 * Contains \Drupal\disqus\Plugin\migrate\source\DisqusComment.
 */

namespace Drupal\disqus\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Row;
use Drupal\user\Entity\User;
use Drupal\migrate\Entity\MigrationInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Disqus comment source using disqus-api.
 *
 * @MigrateSource(
 *   id = "disqus_source"
 * )
 */
class DisqusComment extends SourcePluginBase {

  /**
   * Iterator.
   *
   * @var \ArrayIterator
   */
  protected $iterator;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The disqus.settings configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Constructs Disqus comments destination plugin.
   *
   * @param array $configuration
   * A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   * The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   * The plugin implemetation definition.
   * @param \Drupal\migrate\Entity\MigrationInterface $migration
   * The migration.
   * @param \Psr\Log\LoggerInterface $logger
   * A logger instance.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   * The config factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, LoggerInterface $logger, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
 $this->logger = $logger;
 $this->config = $config_factory->get('disqus.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory')->get('disqus'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['id']['type'] = 'int';
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return array(
      'id' => $this->t('Comment ID.'),
      'pid' => $this->t('Parent comment ID. If set to null, this comment is not a reply to an existing comment.'),
      'entity_id' => $this->t('The entity to which this comment belongs.'),
      'entity_type' => $this->t('The entity-type of the entity on which this comment belongs.'),
      'name' => $this->t("The comment author's name."),
      'user_id' => $this->t('The disqus user-id of the author who commented.'),
      'email' => $this->t("The comment author's email address."),
      'url' => $this->t("The author's home page address	."),
      'ipAddress' => $this->t("The author's IP address."),
      'isAnonymous' => $this->t('If true, this comments has been posted by an anonymous user.'),
      'isApproved' => $this->t('If the comment is approved or not.'),
      'createdAt' => $this->t('The time that the comment was created.'),
      'comment' => $this->t('The comment body.'),
      'isEdited' => $this->t('Boolean value indicating if the comment has been edited or not.'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator() {
    if (!isset($this->iterator)) {
      $disqus = disqus_api();
      if ($disqus) {
        try {
          $posts = $disqus->forums->listPosts(array('forum' => $this->config->get('disqus_domain')));
        }
        catch (Exception $exception) {
          drupal_set_message(t('There was an error loading the forum details. Please check you API keys and try again.', 'error'));
          $this->logger->error('Error loading the Disqus PHP API. Check your forum name.', array());
          return FALSE;
        }
        
        $items = array();
        foreach ($posts as $post) {
          $id = $post['id'];
          $items[$id]['id'] = $id;
          $items[$id]['pid'] = $post['parent'];
          $thread = $disqus->threads->details(array('thread' => $post['thread']));
          $identifier = $thread['identifier'];
          $id_parts = explode("/", $thread['identifier']);
          $items[$id]['entity_type'] = $id_parts[0];
          $items[$id]['entity_id'] = $id_parts[1];
          $items[$id]['name'] = $post['author']['name'];
          $items[$id]['email'] = $post['author']['email'];
          $items[$id]['user_id'] = $post['author']['id'];
          $items[$id]['url'] = $post['author']['url'];
          $items[$id]['ipAddress'] = $post['ipAddress'];
          $items[$id]['isAnonymous'] = $post['author']['isAnonymous'];
          $items[$id]['createdAt'] = $post['createdAt'];
          $items[$id]['comment'] = $post['message'];
          $items[$id]['isEdited'] = $post['isEdited'];
        }
      }
      $this->iterator = new \ArrayIterator($items);
    }
    return $this->iterator;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $row->setSourceProperty('uid', 0);
    $email = $row->getSourceProperty('email');
    $entity_query = \Drupal::entityQuery('user');
    $user = $entity_query->condition('mail', $email)->execute();
    if($user) {
      $row->setSourceProperty('uid', key($user));
    }
    return parent::prepareRow($row);
  }
}

