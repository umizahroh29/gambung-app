<?php

namespace App\Http\Controllers;

use App\JiCash;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\JiCashHistory;
use Alert;

class JiCashController extends Controller
{
  public function __construct()
  {
    $this->middleware('auth');
  }

  public function index()
  {
    $histories = JiCashHistory::where('transaction_type','Topup')->orderBy('id', 'DESC')->get();
    return view('admin.jicash', ['histories' => $histories]);
  }

  public function get_proof_jicash(Request $request)
  {
    $data = JiCashHistory::find($request->id);
    echo json_encode($data);
  }

    public function verification(Request $request)
    {
        $ji_cash_history = JiCashHistory::find($request->history_id);
        $ji_cash_history->update([
            'is_topup_approved' => 'OPTYS'
        ]);

        $ji_cash_data = JiCash::find($ji_cash_history->ji_cash_id);

        JiCash::where('id', $ji_cash_data->id)
            ->update([
                'balance' => $ji_cash_data->balance + $ji_cash_history->amount,
                'updated_at' => Carbon::now()
            ]);

        Alert::success('Berhasil', 'Berhasil diverifikasi');
        return redirect()->back();
    }

    public function cancel(Request $request)
    {
      $ji_cash_history = JiCashHistory::find($request->history_id);
      $ji_cash_history->update([
          'topup_proof_image' => null,
          'updated_at' => Carbon::now(),
      ]);
      Alert::success('Berhasil', 'Berhasil ditolak');
      return redirect()->back();
    }
}
