<?php
/**
 * Copyright (c) 2016. On Tap Networks Limited.
 */

namespace OnTap\MasterCard\Gateway\Request\Hosted;

use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\CartFactory;
use Magento\Payment\Gateway\Data\Quote\QuoteAdapter;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order\Payment;
use OnTap\MasterCard\Gateway\Config\ConfigFactory;

class OrderDataBuilder implements BuilderInterface
{
    /**
     * @var Cart
     */
    private $cart;

    /**
     * @var ConfigFactory
     */
    protected $configFactory;

    /**
     * OrderDataBuilder constructor.
     * @param CartFactory $cartFactory
     * @param ConfigFactory $configFactory
     */
    public function __construct(CartFactory $cartFactory, ConfigFactory $configFactory)
    {
        $this->cart = $cartFactory->create();
        $this->configFactory = $configFactory;
    }

    /**
     * @return array
     */
    protected function getItemData()
    {
        $data = [];
        /** @var \Magento\Quote\Model\Quote\Item $item */
        foreach ($this->cart->getItems() as $item) {
            if ($item->getParentItemId() !== null) {
                continue;
            }
            $unitPrice = $item->getRowTotal() - $item->getTotalDiscountAmount();
            $data[] = [
                'name' => $item->getName(),
                'description' => $item->getDescription(),
                'sku' => $item->getSku(),
                'unitPrice' => sprintf('%.2F', $unitPrice + $item->getDiscountTaxCompensationAmount()),
                'quantity' => 1,
                //'unitTaxAmount' => 0,
            ];
        }

        return $data;
    }

    /**
     * Builds ENV request
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);

        /* @var QuoteAdapter $order */
        $order = $paymentDO->getOrder();

        $storeId = $order->getStoreId();

        /** @var Payment $payment */
        $payment = $paymentDO->getPayment();

        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $payment->getQuote();
        $quote->collectTotals();

        $config = $this->configFactory->create();
        $config->setMethodCode($payment->getMethod());

        $shipping = $quote->getShippingAddress();

        $taxAmount = $quote->isVirtual()
            ? $quote->getBillingAddress()->getTaxAmount()
            : $shipping->getTaxAmount();

        return [
            'order' => [
                'amount' => sprintf('%.2F', $quote->getGrandTotal()),
                'currency' => $order->getCurrencyCode(),
                'id' => $order->getOrderIncrementId(),
                'item' => $this->getItemData(),
                'shippingAndHandlingAmount' => $shipping->getShippingAmount(),
                'taxAmount' => $taxAmount,
                'notificationUrl' => $config->getWebhookNotificationUrl($storeId),
            ]
        ];
    }
}
