<?php

namespace App\Http\Controllers;

use Auth;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Wishlist;
use App\Models\Cart;
use App\Helpers\LocationHelper;
use Helper;

class CartController extends Controller
{
    protected $product = null;

    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    public function addToCart(Request $request)
    {
        if (empty($request->slug)) {
            request()->session()->flash('error', 'Invalid Product');
            return back();
        }

        $product = Product::where('slug', $request->slug)->first();
        if (empty($product)) {
            request()->session()->flash('error', 'Invalid Product');
            return back();
        }

        $location_price = $product->getPriceForLocation(LocationHelper::getCountryCode()) ?? $product->price;
        $discounted_price = $location_price - ($location_price * ($product->discount ?? 0) / 100);

        $already_cart = Cart::where('user_id', auth()->user()->id)
            ->where('order_id', null)
            ->where('product_id', $product->id)
            ->first();

        if ($already_cart) {
            $already_cart->quantity = $already_cart->quantity + 1;
            $already_cart->amount = $discounted_price * $already_cart->quantity;
            if ($already_cart->product->stock < $already_cart->quantity || $already_cart->product->stock <= 0) {
                return back()->with('error', 'Stock not sufficient!');
            }
            $already_cart->save();
        } else {
            $cart = new Cart;
            $cart->user_id = auth()->user()->id;
            $cart->product_id = $product->id;
            $cart->price = $discounted_price;
            $cart->quantity = 1;
            $cart->amount = $discounted_price;
            if ($cart->product->stock < $cart->quantity || $cart->product->stock <= 0) {
                return back()->with('error', 'Stock not sufficient!');
            }
            $cart->save();
            Wishlist::where('user_id', auth()->user()->id)
                ->where('cart_id', null)
                ->update(['cart_id' => $cart->id]);
        }

        request()->session()->flash('success', 'Product successfully added to cart');
        return back();
    }

    public function singleAddToCart(Request $request)
    {
        $request->validate([
            'slug' => 'required',
            'quant' => 'required',
        ]);

        $product = Product::where('slug', $request->slug)->first();
        if (empty($product)) {
            request()->session()->flash('error', 'Invalid Product');
            return back();
        }

        if ($product->stock < $request->quant[1]) {
            return back()->with('error', 'Out of stock, You can add other products.');
        }

        if ($request->quant[1] < 1) {
            request()->session()->flash('error', 'Invalid Quantity');
            return back();
        }

        $location_price = $product->getPriceForLocation(LocationHelper::getCountryCode()) ?? $product->price;
        $discounted_price = $location_price - ($location_price * ($product->discount ?? 0) / 100);

        $already_cart = Cart::where('user_id', auth()->user()->id)
            ->where('order_id', null)
            ->where('product_id', $product->id)
            ->first();

        if ($already_cart) {
            $already_cart->quantity = $already_cart->quantity + $request->quant[1];
            $already_cart->amount = $discounted_price * $already_cart->quantity;
            if ($already_cart->product->stock < $already_cart->quantity || $already_cart->product->stock <= 0) {
                return back()->with('error', 'Stock not sufficient!');
            }
            $already_cart->save();
        } else {
            $cart = new Cart;
            $cart->user_id = auth()->user()->id;
            $cart->product_id = $product->id;
            $cart->price = $discounted_price;
            $cart->quantity = $request->quant[1];
            $cart->amount = $discounted_price * $request->quant[1];
            if ($cart->product->stock < $cart->quantity || $cart->product->stock <= 0) {
                return back()->with('error', 'Stock not sufficient!');
            }
            $cart->save();
        }

        request()->session()->flash('success', 'Product successfully added to cart.');
        return back();
    }

    public function cartDelete(Request $request)
    {
        $cart = Cart::find($request->id);
        if ($cart) {
            $cart->delete();
            request()->session()->flash('success', 'Cart successfully removed');
            return back();
        }
        request()->session()->flash('error', 'Error please try again');
        return back();
    }

    public function cartUpdate(Request $request)
    {
        if ($request->quant) {
            $error = [];
            $success = '';
            foreach ($request->quant as $k => $quant) {
                $id = $request->qty_id[$k];
                $cart = Cart::find($id);
                if ($quant > 0 && $cart) {
                    if ($cart->product->stock < $quant) {
                        request()->session()->flash('error', 'Out of stock');
                        return back();
                    }
                    $cart->quantity = ($cart->product->stock > $quant) ? $quant : $cart->product->stock;
                    if ($cart->product->stock <= 0) continue;

                    $location_price = $cart->product->getPriceForLocation(LocationHelper::getCountryCode()) ?? $cart->product->price;
                    $after_price = $location_price - ($location_price * ($cart->product->discount ?? 0) / 100);
                    $cart->price = $after_price;
                    $cart->amount = $after_price * $cart->quantity;
                    $cart->save();
                    $success = 'Cart successfully updated!';
                } else {
                    $error[] = 'Cart Invalid!';
                }
            }
            return back()->with($error)->with('success', $success);
        } else {
            return back()->with('error', 'Cart Invalid!');
        }
    }

    public function checkout(Request $request)
    {
        return view('frontend.pages.checkout');
    }
}
?>
