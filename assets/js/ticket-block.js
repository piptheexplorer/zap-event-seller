(function(){
  function init(container){
    const form = container.querySelector('.ets-ticket-form');
    const button = container.querySelector('.ets-pay-button');
    if (!form || !button) return;

    const totalEl = container.querySelector('.ets-total-amount');
    const qtyInputs = container.querySelectorAll('.ets-qty-input');
    const termsCheckbox = container.querySelector('.ets-terms-checkbox');

    function hasTicketSelected(){
      let total = 0;
      qtyInputs.forEach(input => {
        const qty = parseInt(input.value, 10) || 0;
        const price = parseFloat(input.dataset.price) || 0;
        total += qty * price;
      });
      return total > 0;
    }

    function updateButtonState(){
      const termsOk = termsCheckbox ? termsCheckbox.checked : true;
      button.disabled = !hasTicketSelected() || !termsOk;
    }

    function updateTotal(){
      let total = 0;
      qtyInputs.forEach(input => {
        const qty = parseInt(input.value, 10) || 0;
        const price = parseFloat(input.dataset.price) || 0;
        total += qty * price;
      });
      if (totalEl) totalEl.textContent = '£' + total.toFixed(2);
      updateButtonState();
    }

    qtyInputs.forEach(input => input.addEventListener('input', updateTotal));
    if (termsCheckbox) termsCheckbox.addEventListener('change', updateButtonState);
    updateTotal();

    const restUrl = container.dataset.etsRestUrl;
    const stripeKey = container.dataset.etsStripePk;
    if (!restUrl || !stripeKey || typeof Stripe === 'undefined') return;

    const stripe = Stripe(stripeKey);

    button.addEventListener('click', async function(e){
      e.preventDefault();

      if (button.disabled) return;

      const nameField = form.querySelector('[name="ets_name"]');
      const emailField = form.querySelector('[name="ets_email"]');

      if (!nameField || !emailField || !nameField.value.trim() || !emailField.value.trim()) {
        alert('Please enter your name and email.');
        return;
      }

      if (termsCheckbox && !termsCheckbox.checked) {
        alert('Please agree to the terms and conditions.');
        return;
      }

      const formData = new FormData(form);
      const originalText = button.textContent;
      button.disabled = true;
      button.textContent = 'Redirecting to Stripe...';

      try {
        const response = await fetch(restUrl, { method: 'POST', body: formData });
        const data = await response.json();

        if (!response.ok || data.error) {
          alert(data.error || 'Unable to start checkout. Please try again.');
          button.textContent = originalText;
          updateButtonState();
          return;
        }

        const result = await stripe.redirectToCheckout({ sessionId: data.id });
        if (result.error) {
          alert(result.error.message);
          button.textContent = originalText;
          updateButtonState();
        }
      } catch (error) {
        console.error(error);
        alert('An unexpected error occurred.');
        button.textContent = originalText;
        updateButtonState();
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.ets-ticket-block').forEach(init);
  });
})();
