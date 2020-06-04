<?php

namespace App\Http\Controllers;

use App\JiCash;
use App\JiCashHistory;
use App\TransactionDetail;
use App\TransactionHistory;
use App\TransactionPayment;
use App\Voucher;
use Illuminate\Http\Request;
use App\Notification;
use Auth;
use Carbon\Carbon;
use Alert;
use App\Transaction;
use DB;

class TransaksiController extends Controller
{
  public function __construct()
  {
    $this->middleware('auth');
  }

  public function index()
  {
    $transaction = Transaction::with(['detail.product.store', 'payment', 'users'])
    ->orderBy('id', 'DESC')
    ->get();

    foreach ($transaction as $trans) {

        $trans->isoverdue = ($trans->payment['deadline_proof'] < \Carbon\Carbon::now()) ? 'OPTYS' : 'OPTNO';


      $detail_cancel_buyer = TransactionDetail::where([
        ['transaction_code', '=', $trans->code],
        ['shipping_status', '=', 'OPTCC'],
        ])->count();

        $detail_cancel_admin = TransactionDetail::where([
          ['transaction_code', '=', $trans->code],
          ['shipping_status', '=', 'OPTAC'],
          ])->count();

          $detail_refund_admin = TransactionDetail::where([
            ['transaction_code', '=', $trans->code],
            ['shipping_status', '=', 'OPTFL'],
            ])->count();

          $trans->isCancelledBuyer = ($detail_cancel_buyer > 0) ? 'OPTYS' : 'OPTNO';
          $trans->isCancelledAdmin = ($detail_cancel_admin > 0) ? 'OPTYS' : 'OPTNO';
          $trans->isRefundAdmin = ($detail_refund_admin > 0) ? 'OPTYS' : 'OPTNO';

          $statusVerify = TransactionPayment::where([
            'transaction_code'=> $trans->code
            ])->first();

          if ($statusVerify != null) {
              $dateVerify = Carbon::parse($statusVerify->verified_date);
              $diff = $dateVerify->diffInDays(Carbon::now());
              $trans->isOverdueSeller = ($statusVerify->verified_status == "OPTYS" AND $diff >= 1) ? 'OPTYS' : 'OPTNO';
          }
        }

        return view('admin.transaksi', ['transaction' => $transaction]);
      }

      public function get_detail_transaction(Request $request)
      {
        $data = Transaction::with(['detail.product.store', 'users'])->where('code', $request->code)->first();
        echo json_encode($data);
      }

      public function get_proof_transaction(Request $request)
      {
        $data = TransactionPayment::where('transaction_code', $request->code)->first();
        echo json_encode($data);
      }

      public function verification(Request $request)
      {
        $code = $request->transaction_code;

        TransactionPayment::where('transaction_code', $code)->update([
            'verified_status' => 'OPTYS',
            'verified_date' => Carbon::now(),
            'updated_by' => Auth::user()->username,
            'updated_at' => Carbon::now(),
            'updated_process' => 'dalam proses'
        ]);

        TransactionDetail::where('transaction_code', $code)
            ->update([
                'status' => 'dalam proses'
            ]);

        $id = Transaction::where('code', $code)
        ->get();

        $transaction = Transaction::with('detail')
        ->where('code', $code)
        ->get();

        $ji_cash_data = JiCash::where('username', $transaction[0]->username)->first();
        if ($transaction[0]->voucher_code != null) {
            $voucher = Voucher::where('code', $transaction[0]->voucher_code)->first();
            if ($voucher->type == 'VCRCB') {
                JiCashHistory::create([
                    'ji_cash_id' => $ji_cash_data->id,
                    'transaction_type' => 'Cashback',
                    'amount' => $transaction[0]->discount_amount,
                    'created_by' => Carbon::now(),
                    'updated_by' => Carbon::now()
                ]);

                JiCash::where('id', $ji_cash_data->id)
                    ->update([
                        'balance' => $ji_cash_data->balance + $transaction[0]->discount_amount,
                        'updated_at' => Carbon::now()
                    ]);
            }
        }

        Notification::insert([
          'id_users' => $id[0]->users->id,
          'notification_message' => "Transaksi ".$code." sedang diproses oleh penjual.",
          'info' => 'notification',
          'notification_read' => 'OPTNO',
          'created_at' => Carbon::now(),
        ]);

        foreach ($transaction[0]->detail as $detail) {

          Notification::insert([
            'id_users' => $detail->product->store->users->id,
            'notification_message' => "Transaksi ".$code." sudah dikonfimasi admin",
            'info' => 'notification',
            'notification_read' => 'OPTNO',
            'created_at' => Carbon::now(),
          ]);

        }

        Alert::success('Berhasil', 'Berhasil Konfirmasi Transaksi');
        return redirect()->route('transaction.index');
      }

      public function transaction_list()
      {
        $transactions = TransactionDetail::with('transaction.users', 'product.store')->whereHas('product.store', function ($q) {
          $q->where('username', Auth::user()->username);
        })->whereHas('transaction.payment', function ($q) {
          $q->where('verified_status', 'OPTYS');
        })->get();

        return view('seller.pesanan', compact('transactions'));
      }

      public function verification_delivery(Request $request)
      {
        $code = $request->transaction_code;
        $detail_id = $request->transaction_detail_id;

        TransactionDetail::where('id', $detail_id)->update([
          'shipping_status' => 'OPTSD',
          'shipping_no' => $request->shipping_no,
          'updated_by' => Auth::user()->username,
          'updated_at' => Carbon::now(),
            'status' => 'pengiriman'
        ]);

        TransactionPayment::where('transaction_code', $code)->update([
            'updated_by' => Auth::user()->username,
            'updated_at' => Carbon::now(),
            'updated_process' => 'pengiriman'
        ]);

        $id = Transaction::where('code', $code)
        ->get();

        Notification::insert([
          'id_users' => $id[0]->users->id,
          'notification_message' => "Transaksi ".$code." sudah masuk dikirim, jangan lupa menekan tombol diterima jika sudah sampai.",
          'info' => 'notification',
          'notification_read' => 'OPTNO',
          'created_at' => Carbon::now(),
        ]);

        Alert::success('Berhasil', 'Berhasil Konfirmasi Pengiriman');
        return redirect()->route('transaction.list');
      }

      public function cancel(Request $request)
      {
        $code = $request->transaction_code;

        TransactionDetail::where('transaction_code', $code)->update([
          'shipping_status' => 'OPTAC',
          'updated_by' => Auth::user()->username,
          'updated_at' => Carbon::now(),
        ]);

        $details = TransactionDetail::where('transaction_code', $code)->get();
        foreach ($details as $detail) {
          TransactionHistory::insert([
            'transaction_code' => $code,
            'product_code' => $detail->product_code,
            'status' => 'overdue',
            'created_by' => Auth::user()->username,
            'updated_by' => Auth::user()->username,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
          ]);
        }

        Transaction::where('code', $code)->update([
          'total_product' => 0,
          'total_quantity' => 0,
          'total_weight' => 0,
          'shipping_charges' => 0,
          'total_amount' => 0,
          'discount_pct' => 0,
          'discount_amount' => 0,
          'grand_total_amount' => 0,
          'updated_by' => Auth::user()->username,
          'updated_at' => Carbon::now()
        ]);

        Alert::success('Berhasil', 'Berhasil Melakukan Pembatalan');
        return redirect()->route('transaction.index');
      }

      public function kadaluarsa(Request $request)
      {
        $transaction_code = $request->transaction_code;
        $transaction = Transaction::where('code', $transaction_code)->first();
        $username = $transaction->username;
        $totalAmount = $transaction->grand_total_amount;

        DB::beginTransaction();
        TransactionDetail::where('transaction_code', $transaction_code)
        ->update([
          'shipping_status' => 'OPTFL'
        ]);

        $jicash = JiCash::where('username', $username)->first();
        $jicash->update([
          'balance' => $jicash->balance + $totalAmount,
        ]);

        JiCashHistory::insert([
          'ji_cash_id' => $jicash->id,
          'transaction_type' => 'Refund',
          'amount' => $totalAmount,
          'is_topup_approved' => 'OPTNO',
          'is_withdrawal' => 'OPTNO',
          'created_at' => Carbon::now(),
          'updated_at' => Carbon::now(),
        ]);
        DB::commit();

        Alert::success('Berhasil', 'Berhasil refund ke ji-cash');
        return redirect()->back();
      }

      public function decline_proof(Request $request)
      {
        TransactionPayment::where('transaction_code', $request->transaction_code)->update([
          'status_upload' => 'OPTNO',
          'proof_image' => null,
          'proof_date' => null,
          'updated_at' => Carbon::now(),
          'updated_process' => 'bukti ditolak',
        ]);
        
        TransactionDetail::where('transaction_code', $request->transaction_code)
            ->update([
                'status' => 'bukti ditolak'
            ]);

        Alert::success('Berhasil', 'Berhasil menolak bukti pembayaran');
        return redirect()->back();
      }

    }
