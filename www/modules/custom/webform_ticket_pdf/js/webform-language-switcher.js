(function (Drupal, once) {
  Drupal.behaviors.webformLanguageSwitcher = {
    attach(context) {
      once(
        'webform-language-switcher',
        'input[name="language"]',
        context
      ).forEach((radio) => {
        radio.addEventListener('change', function () {
          const form = this.form;

          if (!form) {
            return;
          }

          const url = new URL(form.action, window.location.origin);
          const supportedLanguages = ['nl', 'fr', 'en'];
          const pathParts = url.pathname.split('/').filter(Boolean);

          // Remove the current language prefix.
          if (
            pathParts.length &&
            supportedLanguages.includes(pathParts[0])
          ) {
            pathParts.shift();
          }

          // Add the selected language prefix.
          url.pathname = `/${this.value}/${pathParts.join('/')}`;
          form.action = url.toString();

          // Submit using the Webform Next button.
          const nextButton = form.querySelector(
            '.webform-button--next'
          );

          if (nextButton) {
            form.requestSubmit(nextButton);
          }
        });
      });
    }
  };
})(Drupal, once);
