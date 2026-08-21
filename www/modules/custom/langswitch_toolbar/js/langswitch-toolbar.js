(function (Drupal, once) {
  Drupal.behaviors.langswitchToolbar = {
    attach(context, settings) {
      const urls = settings.langswitchToolbar?.urls || {};

      // Redirect when changing the select.
      once('langswitchToolbarSelect', '.langswitch-toolbar__select', context).forEach((select) => {
        select.addEventListener('change', (e) => {
          const langcode = e.target.value;
          if (urls[langcode]) {
            // Close trays before navigating.
            document
              .querySelectorAll('.toolbar-tray.is-active')
              .forEach((tray) => tray.classList.remove('is-active'));
            document
              .querySelectorAll('.toolbar-bar .toolbar-tab.is-active')
              .forEach((tab) => tab.classList.remove('is-active'));
            document.body.classList.remove('toolbar-tray-open');

            window.location.href = urls[langcode];
          }
        });
      });
    },
  };
})(Drupal, once);
