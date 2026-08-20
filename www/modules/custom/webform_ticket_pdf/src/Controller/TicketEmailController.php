<?php

namespace Drupal\webform_ticket_pdf\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TicketEmailController extends ControllerBase {

  public function send(WebformSubmissionInterface $webform_submission) {
    $webform = $webform_submission->getWebform();

    $handler = NULL;

    foreach ($webform->getHandlers() as $webform_handler) {
      if ($webform_handler->getPluginId() === 'ticket_email') {
        $handler = $webform_handler;
        break;
      }
    }

    $domain = webform_ticket_pdf_get_domain_for_submission($webform_submission);

    if (!$domain) {
      throw new NotFoundHttpException('No ticket domain found for this submission.');
    }

    if (!$handler || !method_exists($handler, 'sendTicketEmail')) {
      throw new NotFoundHttpException('Ticket email handler not found.');
    }

    $sent = $handler->sendTicketEmail($webform_submission);

    if ($sent) {
      $this->messenger()->addStatus($this->t('The ticket has been emailed.'));
    }
    else {
      $this->messenger()->addError($this->t('The ticket email could not be sent.'));
    }

    $request = \Drupal::request();

    $destination = $request->query->get('destination');

    if (!$destination) {
      $destination = $request->headers->get('referer');
    }

    if (!$destination) {
      $destination = Url::fromRoute('webform_ticket_pdf.my_registration')->toString();
    }

    return new RedirectResponse($destination);
  }

}
