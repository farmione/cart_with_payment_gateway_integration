<?php

namespace App\Http\Livewire;

use Omnipay\Omnipay;
use Livewire\Component;
use Illuminate\Http\Request;
use App\Models\Shoppingcart as Cart;
use App\Models\Order;

class Shoppingcart extends Component
{
    public $cartitems;
    public $subtotal = 0;
    public $total = 0;
    public  $tax = 0;

    private $gateway;

    public function __construct()
    {
        $this->gateway = Omnipay::create('PayPal_Rest');
        $this->gateway->setClientId(env('PAYPAL_CLIENT_ID'));
        $this->gateway->setSecret(env('PAYPAL_CLIENT_SECRET'));
        $this->gateway->setTestMode(true);
    }
    public function render()
    {
        $this->cartitems = Cart::with('product')
            ->where(['user_id' => auth()->user()->id])
            ->where('status', '!=', Cart::STATUS['success'])
            ->get();
        $this->subtotal = 0;
        $this->total = 0;
        $this->tax = 0;

        foreach ($this->cartitems as $item) {
            $this->subtotal += $item->product->price * $item->quantity;
        }
        $this->total = $this->subtotal - $this->tax;
        return view('livewire.shoppingcart');
    }

    public function incrementQty($id)
    {
        $cart = Cart::whereId($id)->first();
        $cart->quantity += 1;
        $cart->save();
        session()->flash('success', 'Product quantity updated !!!');
    }

    public function decrementQty($id)
    {
        $cart = Cart::whereId($id)->first();
        if ($cart->quantity > 1) {
            $cart->quantity -= 1;
            $cart->save();
            session()->flash('success', 'Product quantity updated !!!');
        } else {
            session()->flash('success', 'You cannot have less than 1 quantity');
        }
    }

    public function removeItem($id)
    {
        $cart = Cart::whereId($id)->first();
        if ($cart) {
            $cart->delete();
        }
        $this->emit('updateCartCount');
        session()->flash('success', 'Product removed from cart !!!');
    }

    public function checkout()
    {

        try {
            $response = $this->gateway->purchase(array(
                'amount' => $this->total,
                'currency' => env('PAYPAL_CURRENCY'),
                'cancelUrl' => route('payment.cancel'),
                'returnUrl' => route('payment.success')
            ))->send();

            if ($response->isRedirect()) {
                $order = $response->getData();

                if ($order['state'] == 'created') {
                    foreach ($this->cartitems as $item) {
                        $item->status = Cart::STATUS['in_progress'];
                        $item->payment_id = $order['id'];
                        $item->save();
                    }
                    return redirect($order['links'][1]['href']);
                    //return redirect($response->getRedirectUrl());
                }
                session()->flash('error', 'Something went wrong, Please Try again');
            } else {
                return $response->getMessage();
            }
        } catch (\Throwable $th) {
            return $th->getMessage();
        }
    }

    public function cancel(Request $request)
    {
        if ($request->input('token')) {
            (new Cart)->where('payment_id', $request->input('token'))->update(
                [
                    'payment_id' => '',
                    'status' => Cart::STATUS['pending']
                ]
            );
        }
        return redirect()
            ->route('shoppingcart')
            ->with('error', 'Your payment has been cancelled !!!');
    }

    public function success(Request $request)
    {
        if ($request->input('paymentId') && $request->input('PayerID')) {
            $transaction = $this->gateway->completePurchase(array(
                'payer_id' => $request->input('PayerID'),
                'transactionReference' => $request->input('paymentId')
            ));
            $response = $transaction->send();
            // dd($response);
            if ($response->isSuccessful()) {
                // dd($response->getData());
                $items = Cart::where([
                    'user_id' => auth()->user()->id,
                    'payment_id' => $response->getData()['id']
                ])->with('product')->get();

                foreach ($items as $item) {
                    $order = new Order;
                    $order->user_id = auth()->user()->id;
                    $order->product_id = $item->product_id;
                    $order->payment_id = $item->payment_id;
                    $order->amount = $item->product->price * $item->quantity;
                    $order->save();

                    $item->status = Cart::STATUS['success'];
                    $item->save();
                }
                return redirect()
                    ->route('shoppingcart')
                    ->with('success', 'Transaction Completed !!!');
            }
            return redirect()
                ->route('shoppingcart')
                ->with('error', 'Something went wrong !!!');
        }
    }
}
