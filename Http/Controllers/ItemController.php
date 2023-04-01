<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Category;
use App\Item;
use Image;
use Storage;
use Session;

class ItemController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $items = Item::orderBy('title','ASC')->paginate(10);
        return view('items.index')->with('items', $items);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $categories = Category::all()->sortBy('name');
        return view('items.create')->with('categories',$categories);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    { 
        //dd(storage_path());;
        //validate the data
        // if fails, defaults to create() passing errors
        $this->validate($request, ['title'=>'required|string|max:255',
                                   'category_id'=>'required|integer|min:0',
                                   'description'=>'required|string',
                                   'price'=>'required|numeric',
                                   'quantity'=>'required|integer',
                                   'sku'=>'required|string|max:100',
                                   'picture' => 'required|image']); 

        //send to DB (use ELOQUENT)
        $item = new Item;
        $item->title = $request->title;
        $item->category_id = $request->category_id;
        $item->description = $request->description;
        $item->price = $request->price;
        $item->quantity = $request->quantity;
        $item->sku = $request->sku;

        //save image
        if ($request->hasFile('picture')) {
            $image = $request->file('picture');

            $filename = time() . '.' . $image->getClientOriginalExtension();
            $location ='images/items/' . $filename;

            $image = Image::make($image);
            Storage::disk('public')->put($location, (string) $image->encode());
            $item->picture = $filename;
        }

        $item->save(); //saves to DB

        // flash success msg
        Session::flash('success','The item has been added');


        // After image saved, perform two resizes
        $image = $request->file('picture');
        // Copy original img file into two new vars so we can work w/ copy of og img during each resize
        // (reduces img distortion)
        $imageToShrink = $image;
        $imageToGrow = $image;

        // 1: Thumbnail (tn_)
        $imageToShrink = Image::make($imageToShrink);
        $imageToShrink->resize(50, 50, function ($constraint) {
            $constraint->aspectRatio();
        });
        $location ='images/items/' . 'tn_' . $filename;
        Storage::disk('public')->put($location, (string) $imageToShrink->encode());

        // 2: Large (lrg_)
        $imageToGrow = Image::make($imageToGrow);
        $imageToGrow->resize(200, 200, function ($constraint) {
            $constraint->aspectRatio();
        });
        $location ='images/items/' . 'lrg_' . $filename;
        Storage::disk('public')->put($location, (string) $imageToGrow->encode());

        

        //redirect
        return redirect()->route('items.index');     
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $item = Item::find($id);
        if (isset($item->picture)) {
            $oldFilename = $item->picture;
            Storage::delete('public/images/items/'. $oldFilename);
            Storage::delete('public/images/items/'. 'tn_' . $oldFilename);
            Storage::delete('public/images/items/'. 'lrg_' . $oldFilename);                        
        }
        $item->delete();

        Session::flash('success','The item has been deleted');

        return redirect()->route('items.index');

    }
}