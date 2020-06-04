<?php

namespace App\Http\Controllers;

use App\Store;
use App\TransactionDetail;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;
use Auth;
use Carbon\Carbon;
use App\Product;
use App\Transaction;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        if (Auth::check()) {
            if(Auth::user()->role != 'ROLPB') {
                $this->middleware('auth');
            }
        }
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        if (Auth::check()) {
            if(Auth::user()->role == 'ROLPJ') {
                config(['app.locale' => 'id']);
                Carbon::setLocale('id');
                $today = Carbon::now();
                $user_store = Store::select('code')->where('username', Auth::user()->username)->first();
                $transaksi = TransactionDetail::whereHas('product.store', function ($query) use ($user_store, $today) {
                    $query->where('code', $user_store['code']);
                })->where('shipping_status', 'OPTRC')->whereRaw('MONTH(created_at) = ' . $today->month . ' AND YEAR(created_at) = ' . $today->year)
                    ->get();

                return view('seller.home', ['transaksi' => $transaksi]);
            } else if(Auth::user()->role == 'ROLAD' || Auth::user()->role == 'ROLSA') {

                $transaksi = Transaction::whereHas('history', function($query){
                  $query->where('status', 'accepted');
                });

                for ($i=1; $i <= 12; $i++) {
                  $data[$i] = Transaction::whereHas('history', function($query){
                    $query->where('status', 'accepted');
                  })->whereMonth('created_at', $i)->count();
                }

                return view('admin.home', ['transaksi' => $transaksi, 'data' => $data]);
            } else {
                $data['products'] = Product::with(['images' => function ($query) {
                    $query->where('main_image', '=', 'OPTYS');
                }])->limit(6)->get();

                return view('buyer.home', $data);
            }
        } else {
            $data['products'] = Product::with(['images' => function ($query) {
                $query->where('main_image', '=', 'OPTYS');
            }])->limit(6)->get();

            return view('buyer.home', $data);
        }
    }
}
