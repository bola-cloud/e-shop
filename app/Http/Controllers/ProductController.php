<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;

use Illuminate\Support\Str;
use App\Helpers\LocationHelper;


class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $products = Product::getAllProduct();
        return view('backend.product.index', compact('products'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $brands = Brand::get();
        $categories = Category::where('is_parent', 1)->get();
        return view('backend.product.create', compact('categories', 'brands'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'title' => 'required|string',
            'summary' => 'required|string',
            'description' => 'nullable|string',
            'photo' => 'required|string',
            'size' => 'nullable',
            'stock' => 'required|numeric',
            'cat_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'child_cat_id' => 'nullable|exists:categories,id',
            'is_featured' => 'sometimes|in:1',
            'status' => 'required|in:active,inactive',
            'condition' => 'required|in:default,new,hot',
            'discount' => 'nullable|numeric',
            'location_prices' => 'required|array',
            'location_prices.ARE' => 'nullable|numeric',
            'location_prices.KSA' => 'nullable|numeric',
            'location_prices.UAE' => 'nullable|numeric',
        ]);

        $slug = generateUniqueSlug($request->title, Product::class);
        $validatedData['slug'] = $slug;
        $validatedData['is_featured'] = $request->input('is_featured', 0);

        $validatedData['size'] = $request->has('size') ? implode(',', $request->input('size')) : '';

        // Price column is now optional or removed
        $product = Product::create($validatedData);

        if ($request->has('location_prices')) {
            foreach ($request->input('location_prices') as $country => $price) {
                if ($price !== null) {
                    $product->locationPrices()->create([
                        'country_code' => strtoupper($country),
                        'price' => $price,
                    ]);
                }
            }
        }

        return redirect()->route('product.index')->with(
            $product ? 'success' : 'error',
            $product ? 'Product Successfully added' : 'Please try again!!'
        );
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        // Implement if needed
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $brands = Brand::get();
        $product = Product::findOrFail($id);
        $categories = Category::where('is_parent', 1)->get();
        $items = Product::where('id', $id)->get();

        return view('backend.product.edit', compact('product', 'brands', 'categories', 'items'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validatedData = $request->validate([
            'title' => 'required|string',
            'summary' => 'required|string',
            'description' => 'nullable|string',
            'photo' => 'required|string',
            'size' => 'nullable',
            'stock' => 'required|numeric',
            'cat_id' => 'required|exists:categories,id',
            'child_cat_id' => 'nullable|exists:categories,id',
            'is_featured' => 'sometimes|in:1',
            'brand_id' => 'nullable|exists:brands,id',
            'status' => 'required|in:active,inactive',
            'condition' => 'required|in:default,new,hot',
            'discount' => 'nullable|numeric',
            'location_prices' => 'required|array',
            'location_prices.ARE' => 'nullable|numeric',
            'location_prices.KSA' => 'nullable|numeric',
            'location_prices.UAE' => 'nullable|numeric',
        ]);

        $validatedData['is_featured'] = $request->input('is_featured', 0);
        $validatedData['size'] = $request->has('size') ? implode(',', $request->input('size')) : '';

        $status = $product->update($validatedData);

        // تحديث أو إنشاء الأسعار حسب الدولة
        if ($request->has('location_prices')) {
            foreach ($request->input('location_prices') as $country => $price) {
                if ($price !== null) {
                    $product->locationPrices()->updateOrCreate(
                        ['country_code' => strtoupper($country)],
                        ['price' => $price]
                    );
                } else {
                    $product->locationPrices()->where('country_code', strtoupper($country))->delete();
                }
            }
        }

        return redirect()->route('product.index')->with(
            $status ? 'success' : 'error',
            $status ? 'Product Successfully updated' : 'Please try again!!'
        );
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $product = Product::findOrFail($id);

        // حذف الأسعار المرتبطة قبل حذف المنتج
        $product->locationPrices()->delete();

        $status = $product->delete();

        return redirect()->route('product.index')->with(
            $status ? 'success' : 'error',
            $status ? 'Product successfully deleted' : 'Error while deleting product'
        );
    }

}
