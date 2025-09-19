<?php

namespace App\Http\Controllers;

use App\Actions\Subscription\CreateSubscription;
use App\Models\PaymentSetting;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Transaction;
use Exception;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Stripe\Checkout\Session;
use Stripe\Stripe;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class StripeController extends Controller
{
    public function __construct()
    {
        $paymentSetting = PaymentSetting::first();
        if ($paymentSetting && $paymentSetting->stripe_secret) {
            Stripe::setApiKey($paymentSetting->stripe_secret);
        }
    }

    public function purchase(Request $request)
    {
        try {
            $plan = json_decode($request->plan);
            
            if (!$plan || !isset($plan->id) || !isset($plan->currency) || !isset($plan->payable_amount)) {
                throw new \Exception('Invalid plan data provided');
            }

            $user = Auth::user();
            if (!$user) {
                throw new \Exception('User not authenticated');
            }

            $data = [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
            ];

            $unit_amount = ! in_array($plan->currency->code, zeroDecimalCurrencies()) ? removeCommaFromNumbers($plan->payable_amount) * 100 : removeCommaFromNumbers($plan->payable_amount);


            $session = Session::create([
                'payment_method_types' => ['card'],
                'customer_email' => $user->email,
                'line_items' => [[
                    'price_data' => [
                        'currency' => $plan->currency->code,
                        'unit_amount' => $unit_amount,
                        'product_data' => [
                            'name' => $plan->name,
                        ],
                    ],
                    'quantity' => '1',
                ]],
                'mode' => 'payment',
                'client_reference_id' => json_encode($data),
                'success_url' => route('stripe.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('stripe.failed') . '?error=subscription_failed',
            ]);

            return redirect($session->url);
            
        } catch (Exception $e) {
            \Log::error('Stripe purchase failed: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'plan_data' => $request->plan ?? 'null'
            ]);
            
            Notification::make()
                ->danger()
                ->title('Payment Failed')
                ->body('Unable to process payment. Please try again.')
                ->send();

            return redirect()->route('filament.user.pages.manage-subscription');
        }
    }

    public function success(Request $request)
    {
        $sessionId = $request->get('session_id');
        if (empty($sessionId)) {
            throw new UnprocessableEntityHttpException('session_id required');
        }

        try {
            $sessionData = Session::retrieve($sessionId);
            $sessionId = $sessionData->id;
            $data = json_decode($sessionData['client_reference_id'], true);
            $plan = Plan::find($data['plan_id']);

            DB::beginTransaction();

            $transaction = Transaction::create([
                'transaction_id' => $sessionData->payment_intent,
                'amount' => $sessionData->amount_total / 100,
                'type' => Subscription::TYPE_STRIPE,
                'user_id' => $data['user_id'],
                'status' => Transaction::SUCCESS,
                'meta' => $sessionData->toArray(),
            ]);


            $planData = [
                'plan' => $plan ? $plan->toArray() : [],
                'user_id' => getLoggedInUserId(),
                'payment_type' => Subscription::TYPE_STRIPE,
                'transaction_id' => $transaction->id,
            ];
            $subscription = CreateSubscription::run($planData);

            DB::commit();

            if ($subscription) {
                Notification::make()
                    ->success()
                    ->title(__('messages.subscription.subscription_created_successfully'))
                    ->send();

                return redirect()->route('filament.user.pages.manage-subscription');
            }
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    public function failed(Request $request)
    {
        if ($request->error == 'subscription_failed') {
            $redirect = route('filament.user.pages.manage-subscription');
        }
        return view('filament.user.payment.payment-cancel', compact('redirect'));
    }
}
