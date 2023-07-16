import React, {useState} from 'react';
import {withRouter} from 'react-router-dom';
import {IbanElement, useStripe, useElements} from '@stripe/react-stripe-js';
import StatusMessages, {useMessages} from './StatusMessages';

const SepaDebitForm = () => {
  const stripe = useStripe();
  const elements = useElements();
  const [name, setName] = useState('Jenny Rosen');
  const [email, setEmail] = useState('jenny.rosen@example.com');
  const [messages, addMessage] = useMessages();

  const handleSubmit = async (e) => {
    // We don't want to let default form submission happen here,
    // which would refresh the page.
    e.preventDefault();

    if (!stripe || !elements) {
      // Stripe.js has not yet loaded.
      // Make sure to disable form submission until Stripe.js has loaded.
      addMessage('Stripe.js has not yet loaded.');
      return;
    }

    const {error: backendError, clientSecret} = await fetch(
      '/create-payment-intent',
      {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          paymentMethodType: 'sepa_debit',
          currency: 'eur',
        }),
      }
    ).then((r) => r.json());

    if (backendError) {
      addMessage(backendError.message);
      return;
    }

    addMessage('Client secret returned');

    const {
      error: stripeError,
      paymentIntent,
    } = await stripe.confirmSepaDebitPayment(clientSecret, {
      payment_method: {
        sepa_debit: elements.getElement(IbanElement),
        billing_details: {
          name,
          email,
        },
      },
    });

    if (stripeError) {
      // Show error to your customer (e.g., insufficient funds)
      addMessage(stripeError.message);
      return;
    }

    // Initially the test PaymentIntent will be in the `processing` state.
    // We'll refetch the payment intent client-side after 5 seconds to show
    // that it successfully transitions to the `succeeded` state.
    //
    // In practice, you should use webhook notifications for fulfillment.
    if(paymentIntent.status === 'processing') {
      addMessage(
        `Payment processing: ${paymentIntent.id} check webhook events for fulfillment.`
      );
      addMessage('Refetching payment intent in 5s.');
      setTimeout(async () => {
        const {paymentIntent: pi} = await stripe.retrievePaymentIntent(clientSecret);
        addMessage(`Payment (${pi.id}): ${pi.status}`);
      }, 5000)
    } else {
      addMessage(`Payment (${paymentIntent.id}): ${paymentIntent.status}`);
    }

  };

  return (
    <>
      <h1>SEPA Direct Debit</h1>

      <p>
        <h4>Try a <a href="https://stripe.com/docs/testing#sepa-direct-debit">test IBAN account number</a>:</h4>
        <div>
          <code>DE89370400440532013000</code>
        </div>
        <div>
          <code>IE29AIBK93115212345678</code>
        </div>
      </p>

      <form id="payment-form" onSubmit={handleSubmit}>
        <label htmlFor="name">Name</label>
        <input
          id="name"
          value={name}
          onChange={(e) => setName(e.target.value)}
          required
        />

        <label htmlFor="email">Email Address</label>
        <input
          id="email"
          type="email"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          required
        />

        <label htmlFor="iban-element">Bank Account</label>
        <IbanElement
          id="iban-element"
          options={{supportedCountries: ['SEPA']}}
        />

        <button type="submit">Pay</button>

        <div id="error-message" role="alert"></div>

        <div id="mandate-acceptance">
          By providing your bank account details and confirming this payment,
          you agree to this Direct Debit Request and the
          <a href="https://stripe.com/au-becs-dd-service-agreement/legal">
            Direct Debit Request service agreement
          </a>
          , and authorise Stripe Payments Australia Pty Ltd ACN 160 180 343
          Direct Debit User ID number 507156 (“Stripe”) to debit your account
          through the Bulk Electronic Clearing System (BECS) on behalf of
          <strong>INSERT YOUR BUSINESS NAME HERE</strong> (the "Merchant") for
          any amounts separately communicated to you by the Merchant. You
          certify that you are either an account holder or an authorised
          signatory on the account listed above.
        </div>
      </form>

      <StatusMessages messages={messages} />
    </>
  );
};

export default withRouter(SepaDebitForm);
