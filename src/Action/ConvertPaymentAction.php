<?php

/*
 * This file has been created by developers from BitBag.
 * Feel free to contact us once you face any issues or want to start
 * another great project.
 * You can find more information about us on https://bitbag.shop and write us
 * an email on mikolaj.krol@bitbag.pl.
 */

declare(strict_types=1);

namespace BitBag\SyliusMultiSafepayPlugin\Action;

use BitBag\SyliusMultiSafepayPlugin\Action\Api\ApiAwareTrait;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Sylius\Bundle\PayumBundle\Provider\PaymentDescriptionProviderInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Payum\Core\Request\Convert;
use Payum\Core\Bridge\Spl\ArrayObject;

final class ConvertPaymentAction implements ActionInterface, GatewayAwareInterface, ApiAwareInterface
{
    use GatewayAwareTrait, ApiAwareTrait;

    /** @var PaymentDescriptionProviderInterface */
    private $paymentDescriptionProvider;

    public function __construct(PaymentDescriptionProviderInterface $paymentDescriptionProvider)
    {
        $this->paymentDescriptionProvider = $paymentDescriptionProvider;
    }

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        /** @var PaymentInterface $payment */
        $payment = $request->getSource();

        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        $customer = $order->getCustomer();
        $shippingAddress = $order->getShippingAddress();
        $billingAddress = $order->getBillingAddress();

        $details = ArrayObject::ensureArrayObject($payment->getDetails());

        $details['paymentData'] = [
            'type' => $this->multiSafepayApiClient->getType(),
            'order_id' => sprintf('%d-%d-%s', $order->getId(), $payment->getId(), $billingAddress->getCountryCode()),
            'currency' => $order->getCurrencyCode(),
            'amount' => $payment->getAmount(),
            'description' => $this->paymentDescriptionProvider->getPaymentDescription($payment),
            'customer' => [
                'locale' => $order->getLocaleCode(),
                'ip_address' => $order->getCustomerIp(),
                'first_name' => $shippingAddress->getFirstName(),
                'last_name' => $shippingAddress->getFirstName(),
                'address1' => $shippingAddress->getStreet(),
                'zip_code' => $shippingAddress->getPostcode(),
                'city' => $shippingAddress->getCity(),
                'country' => $shippingAddress->getCountryCode(),
                'phone' => $shippingAddress->getPhoneNumber(),
                'email' => $customer->getEmail(),
            ],
        ];

        $request->setResult((array) $details);
    }

    public function supports($request): bool
    {
        return
            $request instanceof Convert &&
            $request->getSource() instanceof PaymentInterface &&
            $request->getTo() == 'array'
        ;
    }
}