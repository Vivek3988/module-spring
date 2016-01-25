<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace OnTap\Tns\Gateway\Validator\Direct;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Framework\App\Request;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Payment\Gateway\Helper\SubjectReader;

class ResponseValidator extends AbstractValidator
{
    const APPROVED = 'APPROVED';
    const UNSPECIFIED_FAILURE = 'UNSPECIFIED_FAILURE';
    const DECLINED = 'DECLINED';
    const TIMED_OUT = 'TIMED_OUT';
    const EXPIRED_CARD = 'EXPIRED_CARD';
    const INSUFFICIENT_FUNDS = 'INSUFFICIENT_FUNDS';
    const ACQUIRER_SYSTEM_ERROR = 'ACQUIRER_SYSTEM_ERROR';
    const SYSTEM_ERROR = 'SYSTEM_ERROR';
    const NOT_SUPPORTED = 'NOT_SUPPORTED';
    const DECLINED_DO_NOT_CONTACT = 'DECLINED_DO_NOT_CONTACT';
    const ABORTED = 'ABORTED';
    const BLOCKED = 'BLOCKED';
    const CANCELLED = 'CANCELLED';
    const DEFERRED_TRANSACTION_RECEIVED = 'DEFERRED_TRANSACTION_RECEIVED';
    const REFERRED = 'REFERRED';
    const AUTHENTICATION_FAILED = 'AUTHENTICATION_FAILED';
    const INVALID_CSC = 'INVALID_CSC';
    const LOCK_FAILURE = 'LOCK_FAILURE';
    const SUBMITTED = 'SUBMITTED';
    const NOT_ENROLLED_3D_SECURE = 'NOT_ENROLLED_3D_SECURE';
    const PENDING = 'PENDING';
    const EXCEEDED_RETRY_LIMIT = 'EXCEEDED_RETRY_LIMIT';
    const DUPLICATE_BATCH = 'DUPLICATE_BATCH';
    const DECLINED_AVS = 'DECLINED_AVS';
    const DECLINED_CSC = 'DECLINED_CSC';
    const DECLINED_AVS_CSC = 'DECLINED_AVS_CSC';
    const DECLINED_PAYMENT_PLAN = 'DECLINED_PAYMENT_PLAN';
    const APPROVED_PENDING_SETTLEMENT = 'APPROVED_PENDING_SETTLEMENT';
    const PARTIALLY_APPROVED = 'PARTIALLY_APPROVED';
    const UNKNOWN = 'UNKNOWN';

    /**
     * @var array
     */
    private $gatewayCode = [
        self::APPROVED => 'Transaction Approved',
        self::UNSPECIFIED_FAILURE => 'Transaction could not be processed',
        self::DECLINED => 'Transaction declined by issuer',
        self::TIMED_OUT => 'Response timed out',
        self::EXPIRED_CARD => 'Transaction declined due to expired card',
        self::INSUFFICIENT_FUNDS => 'Transaction declined due to insufficient funds',
        self::ACQUIRER_SYSTEM_ERROR => 'Acquirer system error occurred processing the transaction',
        self::SYSTEM_ERROR => 'Internal system error occurred processing the transaction',
        self::NOT_SUPPORTED => 'Transaction type not supported',
        self::DECLINED_DO_NOT_CONTACT => 'Transaction declined - do not contact issuer',
        self::ABORTED => 'Transaction aborted by payer',
        self::BLOCKED => 'Transaction blocked due to Risk or 3D Secure blocking rules',
        self::CANCELLED => 'Transaction cancelled by payer',
        self::DEFERRED_TRANSACTION_RECEIVED => 'Deferred transaction received and awaiting processing',
        self::REFERRED => 'Transaction declined - refer to issuer',
        self::AUTHENTICATION_FAILED => '3D Secure authentication failed',
        self::INVALID_CSC => 'Invalid card security code',
        self::LOCK_FAILURE => 'Order locked - another transaction is in progress for this order',
        self::SUBMITTED => 'Transaction submitted - response has not yet been received',
        self::NOT_ENROLLED_3D_SECURE => 'Card holder is not enrolled in 3D Secure',
        self::PENDING => 'Transaction is pending',
        self::EXCEEDED_RETRY_LIMIT => 'Transaction retry limit exceeded',
        self::DUPLICATE_BATCH => 'Transaction declined due to duplicate batch',
        self::DECLINED_AVS => 'Transaction declined due to address verification',
        self::DECLINED_CSC => 'Transaction declined due to card security code',
        self::DECLINED_AVS_CSC => 'Transaction declined due to address verification and card security code',
        self::DECLINED_PAYMENT_PLAN => 'Transaction declined due to payment plan',
        self::APPROVED_PENDING_SETTLEMENT => 'Transaction Approved - pending batch settlement',
        self::PARTIALLY_APPROVED => 'The transaction was approved for a lesser amount than requested.',
        self::UNKNOWN => 'Response unknown',
    ];

    const SUCCESS = 'SUCCESS';
    const FAILURE = 'FAILURE';

    /**
     * @var array
     */
    private $resultCode = [
        self::SUCCESS => 'The operation was successfully processed',
        self::PENDING => 'The operation is currently in progress or pending processing',
        self::FAILURE => 'The operation was declined or rejected by the gateway, acquirer or issuer',
        self::UNKNOWN => 'The result of the operation is unknown',
    ];

    /**
     * @var Request\Http
     */
    private $request;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var RemoteAddress
     */
    private $remoteAddress;

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param Request\Http $request
     * @param RemoteAddress $remoteAddress
     * @param ConfigInterface $config
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        Request\Http $request,
        RemoteAddress $remoteAddress,
        ConfigInterface $config,
        OrderRepositoryInterface $orderRepository
    ) {
        parent::__construct($resultFactory);

        $this->request = $request;
        $this->config = $config;
        $this->orderRepository = $orderRepository;
        $this->remoteAddress = $remoteAddress;
    }

    /**
     * Performs domain-related validation for business object
     *
     * @param array $validationSubject
     * @return ResultInterface
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function validate(array $validationSubject)
    {
        $response = SubjectReader::readResponse($validationSubject);
        //$amount = SubjectReader::readAmount($validationSubject);
        $payment = SubjectReader::readPayment($validationSubject);

        if (!isset($response['result'])) {
            return $this->createResult(false, [__("Response does not contain a body.")]);
        }

        if (isset($response['error'])) {
            $msg = sprintf('%s: %s',
                $response['error']['cause'],
                $response['error']['explanation']
            );
            return $this->createResult(false, [__($msg)]);
        }

        $errors = [];

        switch($response['result']) {
            case self::SUCCESS:
                break;

            case self::UNKNOWN:
            case self::PENDING:
            case self::FAILURE:
                $errors[] = $this->resultCode[$response['result']];
                $errors[] = $this->gatewayCode[$response['response']['gatewayCode']];
                break;
        }

        //order.totalAuthorizedAmount
        //order.totalCapturedAmount
        //order.totalRefundedAmount
        //if (number_format((float)$amount, 2) !== number_format($response['order']['amount'], 2)) {
        //    $errors[] = "Amount mismatch";
        //}

        if ($payment->getOrder()->getOrderIncrementId() !== $response['order']['id']) {
            $errors[] = __("OrderID mismatch");
        }

        if ($payment->getOrder()->getCurrencyCode() !== $response['order']['currency']) {
            $errors[] = __("Currency mismatch");
        }

        if (count($errors) > 0) {
            return $this->createResult(false, $errors);
        }

        return $this->createResult(true);
    }
}
