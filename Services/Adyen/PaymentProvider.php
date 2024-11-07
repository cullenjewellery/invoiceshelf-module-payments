<?php
namespace Modules\Payments\Services\Adyen;

use Carbon\Carbon;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Modules\Payments\Services\PaymentInterface;
use Modules\Payments\Helpers\VersionHelper;
use Ramsey\Uuid\Uuid;

if (VersionHelper::checkAppVersion('<', '2.0.0')) {
    VersionHelper::aliasClass('InvoiceShelf\Models\Company', 'App\Models\Company');
    VersionHelper::aliasClass('InvoiceShelf\Models\Currency', 'App\Models\Currency');
    VersionHelper::aliasClass('InvoiceShelf\Models\Payment', 'App\Models\Payment');
    VersionHelper::aliasClass('InvoiceShelf\Models\PaymentMethod', 'App\Models\PaymentMethod');
    VersionHelper::aliasClass('InvoiceShelf\Models\Transaction', 'App\Models\Transaction');
}


class PaymentProvider implements PaymentInterface
{
    private $settings;

    public function __construct()
    {
        $this->settings = PaymentMethod::getSettings(request()->payment_method_id);
    }

    public function generatePayment(Company $company, $invoice)
    {
        $currency = Currency::find($invoice->currency_id);

        $response =  Http::withHeaders([
          'Content-Type' => 'application/json',
          'X-API-Key' => $this->settings['secret'],
          'Idempotency-Key' => (string) Uuid::uuid4(),
        ])->post('https://checkout-test.adyen.com/v71/sessions', [
          'merchantAccount' => 'CullenJewelleryCOM',
          'amount' => [
            'value' => $invoice->total,
            'currency' => $currency->code
          ],
          'returnUrl' => "https://invoices.cullenjewellery.com",
          'reference' => $invoice->invoice_number,
          'countryCode' => "NZ", // TODO: don't hard code this.
        ]);

        if ($response->status() !== 200) {
            return $response->json();
        }

        $response = $response->json();

        $data = [
            'transaction_id' => $response['id'],
            'type' => 'adyen',
            'status' => Transaction::PENDING,
            'transaction_date' => Carbon::now(),
            'invoice_id' => $invoice->id,
            'company_id' => $invoice->company_id
        ];
        $transaction = Transaction::createTransaction($data);
        $response['transaction_unique_hash'] = $transaction->unique_hash;

        return [
            'order' => $response,
            'key' => $this->settings['key'],
            'currency' => $currency
        ];
    }

    public function confirmTransaction(Company $company, $transaction_id, $request)
    {
        $transaction = Transaction::whereTransactionId($transaction_id)->first();

        $sessionId = $request->payment_id;


        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-API-Key' => $this->settings['secret'],
        ])->get("https://checkout-test.adyen.com/v71/sessions/$sessionId");

        if ($response->status() == 200 && $response->json()->status == "completed") {
            $transaction->completeTransaction();

            $payment = Payment::generatePayment($transaction);

            return response()->json([
                'transaction' => $transaction,
                'payment' => $payment
            ]);
        }

        $transaction->failedTransaction();

        return response()->json([
            'transaction' => $transaction,
        ]);
    }

    public function getOrder($transaction_id)
    {
        $response = Http::withHeaders([
                'Accept' => 'application/json',
            ])
            ->withBasicAuth($this->settings['key'], $this->settings['secret'])
            ->get("https://api.razorpay.com/v1/orders/{$transaction_id}")
            ->json();

        return $response;
    }
}
