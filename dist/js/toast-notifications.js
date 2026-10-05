(function () {
  var iconByType = {
    success: 'fa-check-circle',
    error: 'fa-exclamation-circle',
    warning: 'fa-exclamation-triangle',
    info: 'fa-info-circle'
  };

  var stack;

  function getStack() {
    if (!stack) {
      stack = document.createElement('div');
      stack.className = 'app-toast-stack';
      stack.setAttribute('aria-live', 'polite');
      stack.setAttribute('aria-relevant', 'additions');
      document.body.appendChild(stack);
    }
    return stack;
  }

  function dismissToast(toast) {
    toast.classList.remove('is-visible');
    window.setTimeout(function () {
      toast.remove();
    }, 200);
  }

  window.showToast = function (type, message, duration) {
    type = iconByType[type] ? type : 'info';
    message = String(message == null ? '' : message).trim();
    if (!message) return null;

    var toast = document.createElement('div');
    toast.className = 'app-toast app-toast-' + type;
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');

    var icon = document.createElement('i');
    icon.className = 'fa ' + iconByType[type] + ' app-toast-icon';
    icon.setAttribute('aria-hidden', 'true');

    var text = document.createElement('div');
    text.className = 'app-toast-message';
    text.textContent = message;

    var close = document.createElement('button');
    close.className = 'app-toast-close';
    close.type = 'button';
    close.setAttribute('aria-label', 'Dismiss notification');
    close.innerHTML = '&times;';
    close.addEventListener('click', function () {
      dismissToast(toast);
    });

    toast.appendChild(icon);
    toast.appendChild(text);
    toast.appendChild(close);
    getStack().appendChild(toast);
    window.requestAnimationFrame(function () {
      toast.classList.add('is-visible');
    });

    var timeout = Number(duration);
    if (!Number.isFinite(timeout)) timeout = type === 'error' ? 8000 : 5500;
    if (timeout > 0) {
      var timer = window.setTimeout(function () {
        dismissToast(toast);
      }, timeout);
      toast.addEventListener('mouseenter', function () {
        window.clearTimeout(timer);
      }, { once: true });
    }

    return toast;
  };

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.alert.alert-dismissible:not(#alert), .toast-flash').forEach(function (notice) {
      var copy = notice.cloneNode(true);
      copy.querySelectorAll('.close, button').forEach(function (button) {
        button.remove();
      });

      var type = 'info';
      if (notice.classList.contains('alert-danger') || notice.classList.contains('callout-danger') || notice.classList.contains('toast-error')) {
        type = 'error';
      } else if (notice.classList.contains('alert-success') || notice.classList.contains('callout-success') || notice.classList.contains('toast-success')) {
        type = 'success';
      } else if (notice.classList.contains('alert-warning') || notice.classList.contains('toast-warning')) {
        type = 'warning';
      }

      window.showToast(type, copy.textContent.replace(/\s+/g, ' '));
      notice.remove();
    });
  });
})();