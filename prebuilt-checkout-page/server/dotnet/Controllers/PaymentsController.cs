﻿using System;
using System.Collections.Generic;
using System.IO;
using System.Threading.Tasks;
using Microsoft.AspNetCore.Mvc;
using Microsoft.Extensions.Options;
using Stripe;
using Stripe.Checkout;

namespace server.Controllers
{
    public class PaymentsController : Controller
    {
        public readonly IOptions<StripeOptions> options;
        private readonly IStripeClient client;

        public PaymentsController(IOptions<StripeOptions> options)
        {
            this.options = options;
            this.client = new StripeClient(this.options.Value.SecretKey);
        }

        [HttpGet("checkout-session")]
        public async Task<Session> GetCheckoutSession(string sessionId)
        {
            var service = new SessionService(this.client);
            var session = await service.GetAsync(sessionId);
            return session;
        }

        [HttpPost("create-checkout-session")]
        public async Task<IActionResult> CreateCheckoutSession()
        {
            // Pulled from environment variables in the `.env` file. In practice,
            // users often hard code this list of strings representing the types of
            // payment methods that are accepted.
            List<string> paymentMethodTypes = this.options.Value.PaymentMethodTypes;

            // Create new Checkout Session for the order
            // Other optional params include:
            //  [billing_address_collection] - to display billing address details on the page
            //  [customer] - if you have an existing Stripe Customer ID
            //  [customer_email] - lets you prefill the email input in the form
            //  For full details see https:#stripe.com/docs/api/checkout/sessions/create

            //  ?session_id={CHECKOUT_SESSION_ID} means the redirect will have the session ID set as a query param
            var options = new SessionCreateOptions
            {
                SuccessUrl = $"{this.options.Value.Domain}/success.html?session_id={{CHECKOUT_SESSION_ID}}",
                CancelUrl = $"{this.options.Value.Domain}/canceled.html",
                PaymentMethodTypes = paymentMethodTypes,
                Mode = "payment",
                LineItems = new List<SessionLineItemOptions>
                {
                    new SessionLineItemOptions
                    {
                        Quantity = 1,
                        Price = this.options.Value.Price,
                    },
                },
            };

            var service = new SessionService(this.client);
            var session = await service.CreateAsync(options);
            Response.Headers.Add("Location", session.Url);
            return new StatusCodeResult(303);
        }

        [HttpPost("webhook")]
        public async Task<IActionResult> Webhook()
        {
            var json = await new StreamReader(HttpContext.Request.Body).ReadToEndAsync();
            Event stripeEvent;
            try
            {
                stripeEvent = EventUtility.ConstructEvent(
                    json,
                    Request.Headers["Stripe-Signature"],
                    this.options.Value.WebhookSecret
                );
                Console.WriteLine($"Webhook notification with type: {stripeEvent.Type} found for {stripeEvent.Id}");
            }
            catch (Exception e)
            {
                Console.WriteLine($"Something failed {e}");
                return BadRequest();
            }

            if (stripeEvent.Type == "checkout.session.completed")
            {
                var session = stripeEvent.Data.Object as Stripe.Checkout.Session;
                Console.WriteLine($"Session ID: {session.Id}");
                // Take some action based on session.
                // Note: If you need access to the line items, for instance to
                // automate fullfillment based on the the ID of the Price, you'll
                // need to refetch the Checkout Session here, and expand the line items:
                //
                //var options = new SessionGetOptions();
                // options.AddExpand("line_items");
                //
                // var service = new SessionService();
                // Session session = service.Get(session.Id, options);
                //
                // StripeList<LineItem> lineItems = session.LineItems;
                //
                // Read more about expand here: https://stripe.com/docs/expand
            }

            return Ok();
        }
    }
}
