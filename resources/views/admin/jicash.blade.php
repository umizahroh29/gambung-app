@extends('layouts.dashboard-layout')

@section('page', 'Mengelola Transaksi')

@push('css')
<link rel="stylesheet" href="{{ asset('node_modules/prismjs/themes/prism.css') }}">
@endpush

@section('content')
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h4></h4>
        <div class="card-header-action">
          <form>
            <div class="input-group">
              <input type="text" class="form-control" placeholder="Search">
              <div class="input-group-btn">
                <button class="btn btn-primary"><i class="fas fa-search"></i></button>
              </div>
            </div>
          </form>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive gambung-tables">
          <table class="table table-striped">
            <thead>
              <tr>
                <th>No</th>
                <th>Username Pembeli</th>
                <th>Nominal</th>
                <th>Tanggal Beli</th>
                <th>Bukti Bayar</th>
                <th>Aksi</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              @foreach($histories as $history)
              <tr>
                <td>{{ $loop->iteration }} </td>
                <td>{{ $history->jicash->username }}</td>
                <td>{{ $history->amount }}</td>
                <td>
                  {{ $history->created_at }}
                </td>
                @if($history->topup_proof_image != null)
                <td>
                  <a href="#" class="btn btn-outline-primary"
                  onclick="getProof({{ $history->id }})">Lihat</a>
                </td>
                @else
                <td>
                  -
                </td>
                @endif
                @if($history->is_topup_approved != "OPTYS" AND $history->topup_proof_image != null)
                <td>
                  <form action="{{ route('jicash.verification') }}" method="POST" class="d-inline">
                    @csrf
                    <input type="hidden" name="history_id" value="{{ $history->id }}">
                    <button type="button" class="btn btn-outline-primary" name="button" onclick="confirmation($(this))">
                      Confirm
                    </button>
                  </form>
                  <form action="{{ route('jicash.cancel') }}" method="POST" class="d-inline">
                    @csrf
                    <input type="hidden" name="history_id" value="{{ $history->id }}">
                    <button type="button" class="btn btn-outline-danger" name="button" onclick="confirmation($(this))">
                      Cancel
                    </button>
                  </form>
                </td>
                @else
                <td>-</td>
                @endif
                <td>
                  {{ $history->is_topup_approved != "OPTYS" ? 'Belum Approved' : 'Sudah Approved' }}
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@section('add-modal')
<div class="modal fade" id="modal-bukti" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle"
aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
  <div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title">Bukti Pembayaran</h5>
      <button type="button" class="close" data-dismiss="modal" aria-label="Close">
        <span aria-hidden="true">&times;</span>
      </button>
    </div>
    <div class="modal-body text-center">

    </div>
    <div class="modal-footer">
      <button type="submit" class="btn btn-primary" data-dismiss="modal">OK</button>
    </div>
  </div>
</div>
</div>

@endsection


@push('script')
<script src="{{ asset('assets/js/page/bootstrap-modal.js') }}"></script>
<script>
function getProof(id) {
  let proofImgDir = '{{ asset('assets/img/proof') }}';
  $.ajax({
    type: 'POST',
    url: '{{ route('jicash.proof') }}',
    data: {id: id},
    dataType: 'json',
    success: function (data) {
      console.log(data.topup_proof_image);
      $('#modal-bukti .modal-body').empty();

      let proofimg
      if (data.topup_proof_image != null) {
        proofimg = '<img src="' + proofImgDir + data.topup_proof_image + '" alt="Bukti Bayar" id="buktibayar-img" style="height: 600px; width: auto;">';
      }

      $('#modal-bukti .modal-body').append(proofimg);
      $('#modal-bukti').modal('show');
    }
  });
}
</script>
@endpush
