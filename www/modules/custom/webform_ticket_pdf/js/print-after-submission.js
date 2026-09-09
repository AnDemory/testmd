(function (Drupal, once, drupalSettings) {
  function printTicket(printUrl) {
    const oldFrame = document.getElementById('ticket-print-frame');

    if (oldFrame) {
      oldFrame.remove();
    }

    const printFrame = document.createElement('iframe');

    printFrame.id = 'ticket-print-frame';
    printFrame.src = printUrl;
    printFrame.title = Drupal.t('Print ticket');

    Object.assign(printFrame.style, {
      position: 'fixed',
      width: '1px',
      height: '1px',
      right: '0',
      bottom: '0',
      border: '0',
      opacity: '0',
      pointerEvents: 'none'
    });

    document.body.appendChild(printFrame);
  }

  Drupal.behaviors.webformTicketPrint = {
    attach(context) {
      // Handle the Print buttons on the results overview.
      once(
        'ticket-print-button',
        '.ticket-print-button',
        context
      ).forEach((button) => {
        button.addEventListener('click', (event) => {
          event.preventDefault();
          printTicket(button.href);
        });
      });

      // Automatically print after a new submission.
      once(
        'ticket-print-after-submission',
        'body',
        context
      ).forEach(() => {
        const printUrl =
          drupalSettings.webformTicketPdf?.printUrl;

        if (!printUrl) {
          return;
        }

        printTicket(printUrl);

         // Prevent printing again when the overview is refreshed.
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.delete('print_submission');

        window.history.replaceState(
          {},
          '',
          currentUrl.toString()
        );
      });
    }
  };
})(Drupal, once, drupalSettings);
