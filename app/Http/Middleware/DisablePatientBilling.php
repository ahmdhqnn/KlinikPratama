<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DisablePatientBilling
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->is('pelayanan/kasir', 'pelayanan/kasir/*', 'stok/penjualan-langsung', 'stok/penjualan-langsung/*', 'laporan/pendapatan', 'laporan/pendapatan/*', 'master/biaya-admin', 'master/biaya-admin/*', 'master/biaya-pendaftaran', 'master/biaya-pendaftaran/*', 'master/asuransi', 'master/asuransi/*'), 410, 'Modul pembayaran pasien tidak digunakan pada klinik internal.');

        return $next($request);
    }
}
