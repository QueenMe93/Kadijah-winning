<?php
require_once 'shared.php';

// Returning after redirecting to the Afterpay/Clearpay portal.
if(array_key_exists('return', $_GET) && $_GET['return'] == 'true') {
  $paymentIntent = \Stripe\PaymentIntent::retrieve([
    'id' => $_GET['payment_intent']
  ]);
  echo "<p>Payment " . $paymentIntent->id . " has status: " . $paymentIntent->status . '</p>';
  echo "<a href='/sepa-debit.php'>Try SEPA Direct Debit again</a><br>";
  echo "<a href='/'>Restart demo</a>";
  exit;
}

try {
  $paymentIntent = \Stripe\PaymentIntent::create([
    'payment_method_types' => ['sepa_debit'],
    'amount' => 1999,
    'currency' => 'eur',
  ]);
} catch (\Stripe\Exception\ApiErrorException $e) {
  http_response_code(400);
  echo json_encode(['error' => ['message' => $e->getError()->message]]);
  exit;
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode($e);
  exit;
}
?>

<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>SEPA Direct Debit</title>
    <link rel="stylesheet" href="css/base.css" />
    <script src="https://js.stripe.com/v3/"></script>
    <script>
      document.addEventListener('DOMContentLoaded', async () => {
        const stripe = Stripe('<?php echo $config["stripe_publishable_key"] ?>');
        const elements = stripe.elements();
        const iban = elements.create('iban', {
          supportedCountries: ['SEPA'],
        });
        iban.mount('#iban-element');


        const paymentForm = document.querySelector('#payment-form');
        paymentForm.addEventListener('submit', async (e) => {
          // Avoid a full page POST request.
          e.preventDefault();

          // Customer inputs
          const nameInput = document.querySelector('#name');
          const emailInput = document.querySelector('#email');

          // Confirm the payment that was created server side:
          const {error, paymentIntent} = await stripe.confirmSepaDebitPayment(
            '<?php echo $paymentIntent->client_secret ?>', {
              payment_method: {
                sepa_debit: iban,
                billing_details: {
                  name: nameInput.value,
                  email: emailInput.value,
                },
              },
              return_url: `${window.location.origin}/sepa-debit.php?return=true`,
            },
          );
          if(error) {
            alert(error.message);
            return;
          }
          alert(`Payment (${paymentIntent.id}): ${paymentIntent.status}`);
        });
      });
    </script>
  </head>
  <body>
    <main>
      <a href="/">home</a>
      <h1>SEPA Direct Debit</h1>

      <p>
        <h4>Try a <a href="https://stripe.com/docs/testing#sepa-direct-debit">test account</a>:</h4>
        <div>
          <code>DE89370400440532013000</code>
        </div>
        <div>
          <code>IE29AIBK93115212345678</code>
        </div>
      </p>

      <form id="payment-form">
        <label for="name">
          Name
        </label>
        <input id="name" placeholder="Jenny Rosen" value="Jenny Rosen" required />

        <label for="email">
          Email Address
        </label>
        <input id="email" type="email" value="jenny.rosen@example.com" placeholder="jenny.rosen@example.com" required />

        <label for="iban-element">
          IBAN
        </label>
        <div id="iban-element">
          <!-- A Stripe Element will be inserted here. -->
        </div>

        <button type="submit">Pay</button>

        <!-- Display mandate acceptance text. -->
        <div id="mandate-acceptance">
          By providing your payment information and confirming this payment,
          you authorise (A) <strong>{{YOUR BUSINESS NAME}}</strong> and Stripe,
          our payment service provider, to send instructions to your bank to
          debit your account and (B) your bank to debit your account in
          accordance with those instructions. As part of your rights, you are
          entitled to a refund from your bank under the terms and conditions of
          your agreement with your bank. A refund must be claimed within 8
          weeks starting from the date on which your account was debited.  Your
          rights are explained in a statement that you can obtain from your
          bank. You agree to receive notifications for future debits up to 2
          days before they occur.
        </div>

        <!-- Used to display form errors. -->
        <div id="error-message" role="alert"></div>
      </form>

      <div id="messages" role="alert" style="display: none;"></div>
    </main>
  </body>
</html>
