<?php

namespace Drupal\webform_custom_submissions\Plugin\WebformHandler;

use Drupal\webform_custom_submissions\JiraSubmissionHandler;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\webform\WebformSubmissionConditionsValidatorInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use \Exception;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\webformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form submission handler.
 *
 * @WebformHandler(
 *   id = "webform_custom_submissions",
 *   label = @Translation("Travelform Submission Handler"),
 *   category = @Translation("Form Handler"),
 *   description = @Translation("Sends submission data to Jira."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 */
class TravelformHandler extends WebformHandlerBase {
  private $jira_submission_service;

  public function __construct(array $config, $plugin_id, $plugin_definition, LoggerChannelFactoryInterface $logger_factory, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, WebformSubmissionConditionsValidatorInterface $conditions_validator, JiraSubmissionHandler $submission_service) {
    parent::__construct($config, $plugin_id, $plugin_definition, $logger_factory, $config_factory, $entity_type_manager, $conditions_validator);
    $this->jira_submission_service = $submission_service;
  }

  public static function create(ContainerInterface $container, array $config, $plugin_id, $plugin_definition) {
    // Instantiates this form class.
    return new static(
    // Load the service required to construct this class.
      $config,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('webform_submission.conditions_validator'),
      $container->get('webform_custom_submissions.jira_submission_handler')
    );
  }


  public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    parent::validateForm($form, $form_state, $webform_submission);
    // Only run Jira submissions if other form validation has passed.
    if (count($form_state->getErrors()) < 0) {
      try {
        $this->jira_submission_service->submitToJira($webform_submission);
        $this->messenger()->addMessage($this->t('Ticket successfully created: %ticket', [
          '%ticket' => $this->jira_submission_service->getSubmittedTicket(),
        ]));
        if (count($this->jira_submission_service->getUploadedFileNames()) > 0) {
          $this->messenger()->addMessage($this->t('Files successfully uploaded: %files_html', [
            '%files_html' => implode('<br />', $this->jira_submission_service->getUploadedFileNames())
          ]));
        }
      } catch (Exception $e) {
        $form_state->setError($form, $this->t($e->getMessage()));
      }
    }
  }

}